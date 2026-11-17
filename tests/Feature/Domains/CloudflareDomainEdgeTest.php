<?php

namespace Tests\Feature\Domains;

use App\Actions\Domains\DomainLifecycle;
use App\Enums\DomainStatus;
use App\Enums\EdgeStatus;
use App\Jobs\ReleaseEdgeHostname;
use App\Models\Domain;
use App\Services\DomainEdge\DomainEdge;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Support\CreatesWorkspaceDomains;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class CloudflareDomainEdgeTest extends TestCase
{
    use CreatesWorkspaceDomains;
    use RefreshDatabase;

    private const TARGET = 'cname.openlink.test';

    private InMemoryDnsLookup $dns;

    private array $hostnames = [];

    private array $zones = [['id' => 'zone-1', 'name' => 'openlink.test']];

    private ?int $failing = null;

    private string $fallbackStatus = 'active';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'openlink.domain_edge.driver' => 'cloudflare',
            'openlink.domain_edge.secrets' => ['edge-secret'],
            'openlink.domain_edge.cloudflare.zone_id' => 'zone-1',
            'openlink.domain_edge.cloudflare.api_token' => 'cf-token',
        ]);
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');
        $this->dns = $this->fakeDns();
        $this->dns->pointTo(self::TARGET, '104.16.0.10');
        $this->fakeCloudflare();
    }

    public function test_domains_without_any_record_are_not_sent_to_cloudflare(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        Http::assertNothingSent();
        $domain->refresh();
        $this->assertSame(DomainStatus::Failed, $domain->status);
        $this->assertNull($domain->edge_hostname_id);
        $this->assertStringContainsString('starts as soon as Openlink finds the TXT or CNAME record', $domain->dns_check_error);
    }

    public function test_verified_ownership_creates_a_custom_hostname_with_http_validation(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        $this->publishOwnership($domain);

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::OwnershipVerified, $domain->status);
        $this->assertSame('ch-go.example.test', $domain->edge_hostname_id);
        $this->assertSame(EdgeStatus::Waiting, $domain->edge_status);
        $this->assertStringContainsString(self::TARGET, $domain->dns_check_error);
        $this->assertNull($domain->dns_pointed_at);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['hostname'] === 'go.example.test'
            && $request['ssl']['method'] === 'http'
            && $request['ssl']['settings']['min_tls_version'] === '1.2'
            && $request->hasHeader('Authorization', 'Bearer cf-token'));
    }

    public function test_a_cname_to_the_dns_target_starts_issuing_https_before_the_txt_record_is_found(): void
    {
        [, $domain] = $this->workspaceWithDomain();
        $this->dns->aliasTo($domain->hostname, self::TARGET);

        app(DomainLifecycle::class)->check($domain);

        $domain->refresh();
        $this->assertSame('ch-go.example.test', $domain->edge_hostname_id);
        $this->assertSame(EdgeStatus::Issuing, $domain->edge_status);
        $this->assertSame(DomainStatus::Failed, $domain->status);

        $this->hostnames['ch-go.example.test'] = $this->hostname('ch-go.example.test', 'go.example.test', 'active', 'active');
        app(DomainLifecycle::class)->check($domain);

        $domain->refresh();
        $this->assertSame(EdgeStatus::Connected, $domain->edge_status);
        $this->assertSame(DomainStatus::Failed, $domain->status);

        $this->publishOwnership($domain);
        app(DomainLifecycle::class)->check($domain);

        $this->assertSame(DomainStatus::Active, $domain->fresh()->status);
    }

    public function test_a_cname_to_another_target_does_not_start_provisioning(): void
    {
        [, $domain] = $this->workspaceWithDomain();
        $this->dns->aliasTo($domain->hostname, 'elsewhere.example.test');

        app(DomainLifecycle::class)->check($domain);

        $this->assertNull($domain->fresh()->edge_hostname_id);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_domain_becomes_active_once_the_custom_hostname_and_certificate_are_active(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($domain);
        $this->holdHostname($domain, 'ch-1', 'active', 'active');

        app(DomainLifecycle::class)->check($domain);

        $domain->refresh();
        $this->assertSame(DomainStatus::Active, $domain->status);
        $this->assertSame(EdgeStatus::Connected, $domain->edge_status);
        $this->assertNotNull($domain->dns_pointed_at);
        $this->assertNull($domain->dns_check_error);
    }

    public function test_certificate_validation_errors_are_explained_in_plain_language(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($domain);
        $this->dns->aliasTo($domain->hostname, self::TARGET);
        $this->holdHostname($domain, 'ch-1', 'active', 'pending_validation', 'caa_error: CAA records block issuance.');

        app(DomainLifecycle::class)->check($domain);

        $domain->refresh();
        $this->assertSame(EdgeStatus::Issuing, $domain->edge_status);
        $this->assertStringContainsString('A CAA record on go.example.test blocks', $domain->dns_check_error);
        $this->assertSame(DomainStatus::OwnershipVerified, $domain->status);
    }

    public function test_unknown_cloudflare_errors_get_a_generic_explanation(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($domain);
        $this->holdHostname($domain, 'ch-1', 'pending', 'initializing', 'Something odd happened.');

        app(DomainLifecycle::class)->check($domain);

        $this->assertSame('Cloudflare could not issue HTTPS for go.example.test yet. Openlink keeps retrying.', $domain->fresh()->dns_check_error);
    }

    public function test_a_missing_custom_hostname_is_found_again_by_name_or_recreated(): void
    {
        Queue::fake();
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($domain);
        $this->hostnames['ch-existing'] = $this->hostname('ch-existing', 'go.example.test', 'pending', 'pending_validation');
        $domain->forceFill(['edge_hostname_id' => 'ch-gone'])->save();

        app(DomainLifecycle::class)->check($domain);

        $this->assertSame('ch-existing', $domain->fresh()->edge_hostname_id);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_refused_or_throttled_api_calls_never_recreate_the_custom_hostname(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($domain);
        $this->holdHostname($domain, 'ch-1', 'pending', 'pending_validation');

        foreach ([401 => 'instance administrator', 403 => 'instance administrator', 429 => 'limiting requests'] as $status => $reason) {
            $this->failing = $status;

            app(DomainLifecycle::class)->check($domain);

            $domain->refresh();
            $this->assertSame('ch-1', $domain->edge_hostname_id);
            $this->assertStringContainsString($reason, $domain->dns_check_error);
        }

        Http::assertNotSent(fn (Request $request): bool => in_array($request->method(), ['POST', 'DELETE'], true)
            || str_contains($request->url(), 'custom_hostnames?'));
        $this->assertArrayHasKey('ch-1', $this->hostnames);
    }

    public function test_cloudflare_outages_keep_the_domain_waiting_without_failing_the_check(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        $this->publishOwnership($domain);
        $this->failing = 500;

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::OwnershipVerified, $domain->status);
        $this->assertStringContainsString('could not reach Cloudflare', $domain->dns_check_error);
    }

    public function test_deleting_a_domain_removes_its_custom_hostname(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->holdHostname($domain, 'ch-1', 'active', 'active');

        $this->actingAs($user)->delete(route('domains.destroy', $domain))->assertRedirect();

        $this->assertSame([], $this->hostnames);
        $this->assertNull(Domain::query()->find($domain->id));
    }

    public function test_disabling_a_domain_removes_its_custom_hostname(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->holdHostname($domain, 'ch-1', 'active', 'active');

        app(DomainLifecycle::class)->disable($domain);

        $this->assertSame([], $this->hostnames);
        $this->assertNull($domain->fresh()->edge_hostname_id);
    }

    public function test_deleting_a_workspace_removes_the_custom_hostnames_of_its_domains(): void
    {
        [$workspace, $domain] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->holdHostname($domain, 'ch-1', 'active', 'active');

        $workspace->delete();

        $this->assertSame([], $this->hostnames);
        $this->assertNull(Domain::query()->find($domain->id));
    }

    public function test_deleting_an_account_removes_the_custom_hostnames_of_its_workspaces(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->holdHostname($domain, 'ch-1', 'active', 'active');

        $this->actingAs($user)->delete(route('profile.destroy'), ['password' => 'password'])->assertRedirect('/');

        $this->assertSame([], $this->hostnames);
        $this->assertNull(Domain::query()->find($domain->id));
    }

    public function test_turning_a_workspace_domain_into_the_default_domain_removes_its_custom_hostname(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->holdHostname($domain, 'ch-1', 'active', 'active');

        app(DomainLifecycle::class)->ensureDefaultDomain('go.example.test');

        $domain->refresh();
        $this->assertTrue($domain->is_default);
        $this->assertNull($domain->edge_hostname_id);
        $this->assertSame([], $this->hostnames);
    }

    public function test_a_pending_domain_that_loses_both_records_releases_its_custom_hostname(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->holdHostname($domain, 'ch-1', 'pending', 'pending_validation');

        app(DomainLifecycle::class)->check($domain);

        $domain->refresh();
        $this->assertSame(DomainStatus::Failed, $domain->status);
        $this->assertNull($domain->edge_hostname_id);
        $this->assertSame([], $this->hostnames);
    }

    public function test_abandoned_domains_are_released_and_skipped_by_the_scheduled_check(): void
    {
        [, $abandoned] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($abandoned);
        $this->holdHostname($abandoned, 'ch-1', 'pending', 'pending_validation');
        $abandoned->forceFill(['created_at' => now()->subDays(31)])->save();
        [, $recent] = $this->workspaceWithDomain('fresh.example.test');

        $this->artisan('openlink:verify-pending-domains')
            ->expectsOutputToContain('Checked 1 domains.')
            ->expectsOutputToContain('Released 1 abandoned domains')
            ->assertSuccessful();

        $abandoned->refresh();
        $this->assertNull($abandoned->edge_hostname_id);
        $this->assertNull($abandoned->last_checked_at);
        $this->assertSame([], $this->hostnames);
        $this->assertNotNull($recent->fresh()->last_checked_at);
    }

    public function test_abandoned_domains_stay_checkable_from_the_setup_page(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        $domain->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->publishOwnership($domain);

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $this->assertSame('ch-go.example.test', $domain->fresh()->edge_hostname_id);
    }

    public function test_failed_releases_are_queued_and_retried_until_cloudflare_confirms(): void
    {
        Queue::fake();
        [, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->holdHostname($domain, 'ch-1', 'active', 'active');

        $this->actingAs($user)->delete(route('domains.destroy', $domain))->assertRedirect();

        $job = null;
        Queue::assertPushed(ReleaseEdgeHostname::class, function (ReleaseEdgeHostname $pushed) use (&$job): bool {
            $job = $pushed;

            return $pushed->hostnameId === 'ch-1' && $pushed->afterCommit === true;
        });
        $this->assertGreaterThan(1, $job->tries);
        $this->assertNotEmpty($job->backoff());

        $this->failing = 503;

        try {
            app()->call([$job, 'handle']);
            $this->fail('A failed release must throw so the queue retries it.');
        } catch (RequestException $exception) {
            $this->assertSame(503, $exception->response->status());
        }

        $this->assertArrayHasKey('ch-1', $this->hostnames);

        $this->failing = null;
        app()->call([$job, 'handle']);
        $this->assertSame([], $this->hostnames);

        app()->call([$job, 'handle']);
    }

    public function test_recheck_all_provisions_existing_domains_and_lists_their_owners(): void
    {
        [$workspace, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->publishOwnership($domain);

        $this->assertSame(0, Artisan::call('openlink:verify-pending-domains', ['--all' => true]));

        $this->assertMatchesRegularExpression('/go\.example\.test\s*\|\s*active\s*\|\s*'.preg_quote($workspace->name, '/').'\s*\|\s*'.preg_quote($user->email, '/').'/', Artisan::output());
        $domain->refresh();
        $this->assertSame('ch-go.example.test', $domain->edge_hostname_id);
        $this->assertSame(DomainStatus::Active, $domain->status);
    }

    public function test_issuing_domains_are_rechecked_by_the_frequent_run(): void
    {
        [, $issuing] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($issuing);
        $this->holdHostname($issuing, 'ch-1', 'active', 'pending_validation');
        $issuing->forceFill(['edge_status' => EdgeStatus::Issuing])->save();
        [, $waiting] = $this->workspaceWithDomain('waiting.example.test');

        $this->artisan('openlink:verify-pending-domains', ['--issuing' => true])
            ->expectsOutputToContain('Checked 1 domains.')
            ->assertSuccessful();

        $this->assertNotNull($issuing->fresh()->last_checked_at);
        $this->assertNull($waiting->fresh()->last_checked_at);
    }

    public function test_active_domains_that_do_not_reach_the_edge_are_flagged_without_losing_their_status(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->publishOwnership($domain);
        app(DomainLifecycle::class)->check($domain);

        $this->actingAs($user)->get(route('domains.index'))
            ->assertInertia(fn ($page) => $page
                ->where('domains.0.hostname', 'go.example.test')
                ->where('domains.0.status', DomainStatus::Active->value)
                ->where('domains.0.edge_status', EdgeStatus::Waiting->value)
                ->where('domains.0.needs_dns_update', true)
                ->where('domains.0.dns_record.type', 'CNAME')
                ->where('domains.0.dns_record.value', self::TARGET));

        $this->hostnames['ch-go.example.test'] = $this->hostname('ch-go.example.test', 'go.example.test', 'active', 'active');
        app(DomainLifecycle::class)->check($domain);

        $this->actingAs($user)->get(route('domains.index'))
            ->assertInertia(fn ($page) => $page->where('domains.0.needs_dns_update', false));
        $this->assertSame(DomainStatus::Active, $domain->fresh()->status);
    }

    public function test_the_zone_is_discovered_when_the_token_sees_a_single_zone(): void
    {
        config(['openlink.domain_edge.cloudflare.zone_id' => null]);
        $this->zones = [['id' => 'zone-found', 'name' => 'openlink.test']];
        [, $domain] = $this->workspaceWithDomain();
        $this->publishOwnership($domain);

        app(DomainLifecycle::class)->check($domain);
        app(DomainLifecycle::class)->check($domain);

        $this->assertSame('ch-go.example.test', $domain->fresh()->edge_hostname_id);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/zones/zone-found/custom_hostnames'));
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => parse_url($request->url(), PHP_URL_PATH) === '/client/v4/zones'));
    }

    public function test_several_zones_require_an_explicit_zone_id(): void
    {
        config(['openlink.domain_edge.cloudflare.zone_id' => null]);
        $this->zones = [['id' => 'zone-a'], ['id' => 'zone-b']];
        [, $domain] = $this->workspaceWithDomain();
        $this->publishOwnership($domain);

        app(DomainLifecycle::class)->check($domain);

        $domain->refresh();
        $this->assertNull($domain->edge_hostname_id);
        $this->assertStringContainsString('instance administrator', $domain->dns_check_error);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_a_configured_zone_id_overrides_discovery(): void
    {
        [, $domain] = $this->workspaceWithDomain();
        $this->publishOwnership($domain);

        app(DomainLifecycle::class)->check($domain);

        Http::assertNotSent(fn (Request $request): bool => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/client/v4/zones'));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/zones/zone-1/custom_hostnames'));
    }

    public function test_the_dns_target_comes_from_the_fallback_origin(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        $user->forceFill(['is_instance_admin' => true])->save();

        $this->actingAs($user)->get(route('domains.setup', $domain))
            ->assertInertia(fn ($page) => $page
                ->where('domainEdge', 'cloudflare')
                ->where('domain.dns_record.type', 'CNAME')
                ->where('domain.dns_record.value', self::TARGET));
        $this->actingAs($user)->get(route('settings.index'))
            ->assertInertia(fn ($page) => $page->where('managedDnsTarget', self::TARGET));
    }

    public function test_edge_check_passes_when_cloudflare_is_ready(): void
    {
        $this->artisan('openlink:edge-check')
            ->expectsOutputToContain('PASS')
            ->expectsOutputToContain('The domain edge is ready.')
            ->assertSuccessful();
    }

    public function test_edge_check_fails_loudly_on_misconfiguration(): void
    {
        config(['openlink.domain_edge.secrets' => []]);
        $this->failing = 401;

        $this->artisan('openlink:edge-check')
            ->expectsOutputToContain('FAIL')
            ->expectsOutputToContain('OPENLINK_EDGE_SECRET')
            ->expectsOutputToContain('Zone: Read')
            ->assertFailed();
    }

    public function test_edge_check_fails_while_the_fallback_origin_is_not_active(): void
    {
        $this->fallbackStatus = 'pending_deployment';

        $this->artisan('openlink:edge-check')
            ->expectsOutputToContain('pending_deployment')
            ->assertFailed();
    }

    public function test_an_unknown_domain_edge_is_rejected(): void
    {
        config(['openlink.domain_edge.driver' => 'cloudfare']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown OPENLINK_DOMAIN_EDGE value "cloudfare"');

        app(DomainEdge::class);
    }

    public function test_edge_check_reports_an_unknown_domain_edge(): void
    {
        config(['openlink.domain_edge.driver' => 'cloudfare']);

        $this->artisan('openlink:edge-check')
            ->expectsOutputToContain('Unknown OPENLINK_DOMAIN_EDGE value')
            ->assertFailed();
    }

    public function test_the_default_domain_is_never_sent_to_cloudflare(): void
    {
        app(DomainLifecycle::class)->check(app(DomainLifecycle::class)->ensureDefaultDomain('to.openlink.test'));

        Http::assertNothingSent();
    }

    private function publishOwnership(Domain $domain): void
    {
        $this->dns->publishTxt($domain->verificationTxtName(), $domain->verificationTxtValue());
    }

    private function holdHostname(Domain $domain, string $id, string $status, string $sslStatus, ?string $validationError = null): void
    {
        $this->hostnames[$id] = $this->hostname($id, $domain->hostname, $status, $sslStatus, $validationError);
        $domain->forceFill(['edge_hostname_id' => $id])->save();
    }

    private function hostname(string $id, string $hostname, string $status, string $sslStatus, ?string $validationError = null): array
    {
        return [
            'id' => $id,
            'hostname' => $hostname,
            'status' => $status,
            'ssl' => [
                'status' => $sslStatus,
                'validation_errors' => $validationError === null ? [] : [['message' => $validationError]],
            ],
        ];
    }

    private function fakeCloudflare(): void
    {
        Http::fake(function (Request $request) {
            if ($this->failing !== null) {
                return Http::response(['success' => false, 'errors' => [['code' => 10000, 'message' => 'Refused']]], $this->failing);
            }

            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            if ($path === '/client/v4/user/tokens/verify') {
                return Http::response(['success' => true, 'result' => ['status' => 'active']]);
            }

            if ($path === '/client/v4/zones') {
                return Http::response(['success' => true, 'result' => $this->zones]);
            }

            if (str_ends_with($path, '/custom_hostnames/fallback_origin')) {
                return Http::response(['success' => true, 'result' => ['origin' => self::TARGET, 'status' => $this->fallbackStatus]]);
            }

            $id = trim((string) preg_replace('#^/client/v4/zones/[^/]+/custom_hostnames#', '', $path), '/');

            if ($request->method() === 'POST') {
                $created = $this->hostname('ch-'.$request['hostname'], $request['hostname'], 'pending', 'initializing');
                $this->hostnames[$created['id']] = $created;

                return Http::response(['success' => true, 'result' => $created]);
            }

            if ($request->method() === 'GET' && $id === '') {
                $matches = array_values(array_filter($this->hostnames, fn (array $hostname): bool => $hostname['hostname'] === ($query['hostname'] ?? null)));

                return Http::response(['success' => true, 'result' => $matches]);
            }

            if (! isset($this->hostnames[$id])) {
                return Http::response(['success' => false, 'errors' => [['code' => 1436, 'message' => 'Not found']]], 404);
            }

            if ($request->method() === 'DELETE') {
                unset($this->hostnames[$id]);

                return Http::response(['success' => true, 'result' => ['id' => $id]]);
            }

            return Http::response(['success' => true, 'result' => $this->hostnames[$id]]);
        });
    }
}

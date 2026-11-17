<?php

namespace Tests\Feature\Domains;

use App\Actions\Domains\DomainLifecycle;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\CreatesWorkspaceDomains;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class CloudflareDomainEdgeTest extends TestCase
{
    use CreatesWorkspaceDomains;
    use RefreshDatabase;

    private const PREFIX = '/client/v4/zones/zone-1/custom_hostnames';

    private InMemoryDnsLookup $dns;

    private array $hostnames = [];

    private bool $failing = false;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'openlink.domain_edge.driver' => 'cloudflare',
            'openlink.domain_edge.cloudflare.zone_id' => 'zone-1',
            'openlink.domain_edge.cloudflare.api_token' => 'cf-token',
        ]);
        app(InstanceSettings::class)->set('dns_target', 'cname.openlink.test');
        $this->dns = $this->fakeDns();
        $this->fakeCloudflare();
    }

    public function test_domains_without_proven_ownership_are_not_sent_to_cloudflare(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        Http::assertNothingSent();
        $this->assertSame(DomainStatus::Failed, $domain->fresh()->status);
        $this->assertNull($domain->fresh()->edge_hostname_id);
    }

    public function test_verified_ownership_creates_a_custom_hostname_with_http_validation(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        $this->publishOwnership($domain);

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::OwnershipVerified, $domain->status);
        $this->assertSame('ch-go.example.test', $domain->edge_hostname_id);
        $this->assertStringContainsString('cname.openlink.test', $domain->dns_check_error);
        $this->assertNull($domain->dns_pointed_at);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['hostname'] === 'go.example.test'
            && $request['ssl']['method'] === 'http'
            && $request['ssl']['settings']['min_tls_version'] === '1.2'
            && $request->hasHeader('Authorization', 'Bearer cf-token'));
    }

    public function test_domain_becomes_active_once_the_custom_hostname_and_certificate_are_active(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($domain);
        $this->hostnames['ch-1'] = $this->hostname('ch-1', 'go.example.test', 'active', 'active');
        $domain->forceFill(['edge_hostname_id' => 'ch-1'])->save();

        app(DomainLifecycle::class)->check($domain);

        $domain->refresh();
        $this->assertSame(DomainStatus::Active, $domain->status);
        $this->assertNotNull($domain->dns_pointed_at);
        $this->assertNull($domain->dns_check_error);
    }

    public function test_certificate_validation_errors_are_shown_to_the_user(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($domain);
        $this->hostnames['ch-1'] = $this->hostname('ch-1', 'go.example.test', 'active', 'pending_validation', 'CAA records block issuance.');
        $domain->forceFill(['edge_hostname_id' => 'ch-1'])->save();

        app(DomainLifecycle::class)->check($domain);

        $this->assertSame('CAA records block issuance.', $domain->fresh()->dns_check_error);
        $this->assertSame(DomainStatus::OwnershipVerified, $domain->fresh()->status);
    }

    public function test_a_missing_custom_hostname_is_found_again_by_name_or_recreated(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnership($domain);
        $this->hostnames['ch-existing'] = $this->hostname('ch-existing', 'go.example.test', 'pending', 'pending_validation');
        $domain->forceFill(['edge_hostname_id' => 'ch-gone'])->save();

        app(DomainLifecycle::class)->check($domain);

        $this->assertSame('ch-existing', $domain->fresh()->edge_hostname_id);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_cloudflare_outages_keep_the_domain_waiting_without_failing_the_check(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        $this->publishOwnership($domain);
        $this->failing = true;

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::OwnershipVerified, $domain->status);
        $this->assertStringContainsString('could not reach Cloudflare', $domain->dns_check_error);
    }

    public function test_deleting_a_domain_removes_its_custom_hostname(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->hostnames['ch-1'] = $this->hostname('ch-1', 'go.example.test', 'active', 'active');
        $domain->forceFill(['edge_hostname_id' => 'ch-1'])->save();

        $this->actingAs($user)->delete(route('domains.destroy', $domain))->assertRedirect();

        $this->assertSame([], $this->hostnames);
        $this->assertNull(Domain::query()->find($domain->id));
    }

    public function test_disabling_a_domain_removes_its_custom_hostname(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->hostnames['ch-1'] = $this->hostname('ch-1', 'go.example.test', 'active', 'active');
        $domain->forceFill(['edge_hostname_id' => 'ch-1'])->save();

        app(DomainLifecycle::class)->disable($domain);

        $this->assertSame([], $this->hostnames);
        $this->assertNull($domain->fresh()->edge_hostname_id);
    }

    public function test_recheck_all_provisions_existing_active_domains_and_lists_unreachable_ones(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $this->publishOwnership($domain);

        $this->artisan('openlink:verify-pending-domains', ['--all' => true])
            ->expectsOutputToContain('go.example.test')
            ->assertSuccessful();

        $domain->refresh();
        $this->assertSame('ch-go.example.test', $domain->edge_hostname_id);
        $this->assertSame(DomainStatus::Active, $domain->status);
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
            if ($this->failing) {
                return Http::response(['success' => false], 500);
            }

            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $id = trim(substr($path, strlen(self::PREFIX)), '/');
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

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

<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Dns\DnsResolver;
use App\Services\InstanceSettings;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class FakeDnsResolver extends DnsResolver
{
    public array $txt = [];

    public array $ips = [];

    public function txtValues(string $hostname): array
    {
        return $this->txt[$hostname] ?? [];
    }

    public function ipAddresses(string $hostname): array
    {
        return $this->ips[$hostname] ?? [];
    }
}

class DomainSetupTest extends TestCase
{
    use RefreshDatabase;

    private FakeDnsResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = new FakeDnsResolver;
        $this->app->instance(DnsResolver::class, $this->dns);
    }

    public function test_adding_a_domain_redirects_to_the_setup_wizard(): void
    {
        [, , $user] = $this->workspaceAndPendingDomain();

        $response = $this->actingAs($user)->post(route('domains.store'), ['hostname' => 'links.example.test']);

        $domain = Domain::query()->where('hostname', 'links.example.test')->firstOrFail();
        $this->assertSame(Domain::STATUS_PENDING, $domain->status);
        $response->assertRedirect(route('domains.setup', $domain));
    }

    public function test_txt_found_but_dns_not_pointing_marks_ownership_verified(): void
    {
        [, $domain, $user] = $this->workspaceAndPendingDomain();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');

        $this->dns->txt['_openlink.'.$domain->hostname] = ['openlink-verification='.$domain->verification_token];
        $this->dns->ips[$domain->hostname] = ['198.51.100.7'];

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(Domain::STATUS_OWNERSHIP_VERIFIED, $domain->status);
        $this->assertNotNull($domain->dns_check_error);
        $this->assertNull($domain->dns_pointed_at);
        $this->assertFalse($domain->isUsable());
    }

    public function test_txt_found_and_dns_pointing_activates_the_domain(): void
    {
        [, $domain, $user] = $this->workspaceAndPendingDomain();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');

        $this->dns->txt['_openlink.'.$domain->hostname] = ['openlink-verification='.$domain->verification_token];
        $this->dns->ips[$domain->hostname] = ['203.0.113.10'];

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(Domain::STATUS_ACTIVE, $domain->status);
        $this->assertNotNull($domain->dns_pointed_at);
        $this->assertNull($domain->dns_check_error);
        $this->assertTrue($domain->isUsable());
    }

    public function test_cloudflare_proxied_domain_activates_after_txt_verification(): void
    {
        [, $domain, $user] = $this->workspaceAndPendingDomain();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');

        $this->dns->txt['_openlink.'.$domain->hostname] = ['openlink-verification='.$domain->verification_token];
        $this->dns->ips[$domain->hostname] = ['104.16.0.10'];

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(Domain::STATUS_ACTIVE, $domain->status);
        $this->assertNotNull($domain->dns_pointed_at);
        $this->assertNull($domain->dns_check_error);
    }

    public function test_missing_txt_marks_verification_failed(): void
    {
        [, $domain, $user] = $this->workspaceAndPendingDomain();

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $this->assertSame(Domain::STATUS_FAILED, $domain->fresh()->status);
    }

    public function test_hostname_dns_target_falls_back_to_default_domain_and_uses_cname(): void
    {
        [, $domain, $user] = $this->workspaceAndPendingDomain();
        app(InstanceSettings::class)->set('default_domain', 'app.example.test');

        $this->dns->txt['_openlink.'.$domain->hostname] = ['openlink-verification='.$domain->verification_token];
        $this->dns->ips[$domain->hostname] = ['203.0.113.10'];
        $this->dns->ips['app.example.test'] = ['203.0.113.10'];

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $this->assertSame(Domain::STATUS_ACTIVE, $domain->fresh()->status);
    }

    public function test_observed_traffic_activates_an_ownership_verified_domain_and_resolves_the_link(): void
    {
        [$workspace, $domain] = $this->workspaceAndPendingDomain();
        $domain->forceFill(['status' => Domain::STATUS_OWNERSHIP_VERIFIED, 'verified_at' => now()])->save();

        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
        ]);

        $this->get('http://'.$domain->hostname.'/launch')
            ->assertRedirect('https://example.com/launch');

        $this->assertSame(Domain::STATUS_ACTIVE, $domain->fresh()->status);
        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_observed_traffic_does_not_activate_pending_or_disabled_domains(): void
    {
        [$workspace, $domain] = $this->workspaceAndPendingDomain();

        ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
        ]);

        $this->get('http://'.$domain->hostname.'/launch')->assertNotFound();
        $this->assertSame(Domain::STATUS_PENDING, $domain->fresh()->status);
    }

    public function test_scheduled_command_rechecks_ownership_verified_domains(): void
    {
        [, $domain] = $this->workspaceAndPendingDomain();
        $domain->forceFill(['status' => Domain::STATUS_OWNERSHIP_VERIFIED, 'verified_at' => now()])->save();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');

        $this->dns->txt['_openlink.'.$domain->hostname] = ['openlink-verification='.$domain->verification_token];
        $this->dns->ips[$domain->hostname] = ['203.0.113.10'];

        $this->artisan('openlink:verify-pending-domains')->assertSuccessful();

        $this->assertSame(Domain::STATUS_ACTIVE, $domain->fresh()->status);
    }

    public function test_dns_metadata_and_status_changes_preserve_short_url_cache_entries(): void
    {
        [$workspace, $domain] = $this->workspaceAndPendingDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
        ]);
        $key = app(ShortUrlCache::class)->key($domain, $link->slug);
        Cache::put($key, $link->id, 600);

        $domain->forceFill([
            'status' => Domain::STATUS_ACTIVE,
            'last_checked_at' => now(),
            'dns_pointed_at' => now(),
        ])->save();

        $this->assertSame($link->id, Cache::get($key));
    }

    public function test_hostname_change_invalidates_old_and_new_short_url_cache_entries(): void
    {
        [$workspace, $domain] = $this->workspaceAndPendingDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
        ]);
        $oldKey = app(ShortUrlCache::class)->key($domain, $link->slug);
        Cache::put($oldKey, $link->id, 600);
        Cache::put('resolution:new.example.test:launch', 999, 600);

        $domain->hostname = 'new.example.test';
        $domain->save();

        $this->assertFalse(Cache::has($oldKey));
        $this->assertFalse(Cache::has('resolution:new.example.test:launch'));
    }

    public function test_domain_deletion_invalidates_short_url_cache_entries(): void
    {
        [$workspace, $domain] = $this->workspaceAndPendingDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
        ]);
        $key = app(ShortUrlCache::class)->key($domain, $link->slug);
        Cache::put($key, $link->id, 600);

        $domain->delete();

        $this->assertFalse(Cache::has($key));
    }

    public function test_setup_page_renders_with_dns_instructions(): void
    {
        [, $domain, $user] = $this->workspaceAndPendingDomain();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');

        $this->actingAs($user)
            ->get(route('domains.setup', $domain))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Domains/Setup')
                ->where('domain.hostname', $domain->hostname)
                ->where('domain.expected_txt_name', '_openlink.'.$domain->hostname)
                ->where('domain.dns_record.type', 'A')
                ->where('domain.dns_record.value', '203.0.113.10'));
    }

    private function workspaceAndPendingDomain(string $hostname = 'go.example.test'): array
    {
        $user = User::factory()->create();
        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Events',
            'slug' => 'events',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);
        $domain = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_PENDING,
            'verification_token' => 'test-token-'.str()->random(12),
        ]);

        return [$workspace, $domain, $user];
    }
}

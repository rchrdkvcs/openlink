<?php

namespace Tests\Feature;

use App\Actions\Domains\DomainLifecycle;
use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesWorkspaceDomains;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class DomainLifecycleTest extends TestCase
{
    use CreatesWorkspaceDomains;
    use RefreshDatabase;

    private InMemoryDnsLookup $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = $this->fakeDns();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');
    }

    public function test_domain_can_be_disabled_through_the_web(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);

        $this->actingAs($user)->post(route('domains.disable', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::Disabled, $domain->status);
        $this->assertNotNull($domain->disabled_at);
        $this->assertFalse($domain->isUsable());
    }

    public function test_domain_can_be_disabled_through_the_api(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/domains/{$domain->id}/disable")
            ->assertOk()
            ->assertJsonPath('data.status', DomainStatus::Disabled->value);

        $this->assertNotNull($domain->fresh()->disabled_at);
    }

    public function test_outsiders_cannot_disable_a_domain(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::Active);
        $outsider = User::factory()->create();

        $this->actingAs($outsider)->post(route('domains.disable', $domain))->assertForbidden();
        Sanctum::actingAs($outsider);
        $this->postJson("/api/v1/domains/{$domain->id}/disable")->assertForbidden();

        $this->assertSame(DomainStatus::Active, $domain->fresh()->status);
    }

    public function test_verifying_a_disabled_domain_does_not_reverify_or_activate_it(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        $this->publishOwnershipAndPointing($domain);
        app(DomainLifecycle::class)->disable($domain);

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();
        Sanctum::actingAs($user);
        $this->postJson("/api/v1/domains/{$domain->id}/verify")
            ->assertOk()
            ->assertJsonPath('data.status', DomainStatus::Disabled->value);

        $domain->refresh();
        $this->assertSame(DomainStatus::Disabled, $domain->status);
        $this->assertNull($domain->verified_at);
        $this->assertNull($domain->dns_pointed_at);
        $this->assertFalse($domain->isUsable());
    }

    public function test_observed_traffic_activates_an_ownership_verified_domain_and_resolves_the_link(): void
    {
        [$workspace, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $link = $this->linkOn($workspace->id, $domain);

        $this->get('http://'.$domain->hostname.'/launch')
            ->assertRedirect('https://example.com/launch');

        $this->assertSame(DomainStatus::Active, $domain->fresh()->status);
        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_observed_traffic_does_not_activate_pending_domains(): void
    {
        [$workspace, $domain] = $this->workspaceWithDomain();
        $this->linkOn($workspace->id, $domain);

        $this->get('http://'.$domain->hostname.'/launch')->assertNotFound();

        $this->assertSame(DomainStatus::Pending, $domain->fresh()->status);
    }

    public function test_observed_traffic_does_not_activate_disabled_domains(): void
    {
        [$workspace, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $domain->forceFill(['disabled_at' => now()])->save();
        $this->linkOn($workspace->id, $domain);

        $this->get('http://'.$domain->hostname.'/launch')->assertNotFound();

        $this->assertSame(DomainStatus::OwnershipVerified, $domain->fresh()->status);
    }

    public function test_scheduled_command_rechecks_ownership_verified_domains(): void
    {
        [, $domain] = $this->workspaceWithDomain(status: DomainStatus::OwnershipVerified);
        $this->publishOwnershipAndPointing($domain);

        $this->artisan('openlink:verify-pending-domains')->assertSuccessful();

        $this->assertSame(DomainStatus::Active, $domain->fresh()->status);
    }

    public function test_scheduled_command_skips_disabled_domains(): void
    {
        [, $domain] = $this->workspaceWithDomain();
        $this->publishOwnershipAndPointing($domain);
        app(DomainLifecycle::class)->disable($domain);

        $this->artisan('openlink:verify-pending-domains')->assertSuccessful();

        $this->assertSame(DomainStatus::Disabled, $domain->fresh()->status);
    }

    public function test_default_domain_is_kept_unique_and_its_token_stable(): void
    {
        $lifecycle = app(DomainLifecycle::class);
        $first = $lifecycle->ensureDefaultDomain('https://Links.Example.test/');
        $token = $first->verification_token;

        $this->assertSame('links.example.test', $first->hostname);
        $this->assertSame($token, $lifecycle->ensureDefaultDomain('links.example.test')->verification_token);

        $second = $lifecycle->ensureDefaultDomain('go.example.test');

        $this->assertSame(DomainStatus::Active, $second->status);
        $this->assertSame([$second->id], Domain::query()->where('is_default', true)->pluck('id')->all());
    }

    private function publishOwnershipAndPointing(Domain $domain): void
    {
        $this->dns
            ->publishTxt($domain->verificationTxtName(), $domain->verificationTxtValue())
            ->pointTo($domain->hostname, '203.0.113.10');
    }

    private function linkOn(int $workspaceId, Domain $domain): ShortLink
    {
        return ShortLink::create([
            'workspace_id' => $workspaceId,
            'domain_id' => $domain->id,
            'slug' => 'launch',
            'destination_url' => 'https://example.com/launch',
        ]);
    }
}

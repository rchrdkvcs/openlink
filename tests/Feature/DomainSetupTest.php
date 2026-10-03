<?php

namespace Tests\Feature;

use App\Enums\DomainStatus;
use App\Models\Domain;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesWorkspaceDomains;
use Tests\Support\InMemoryDnsLookup;
use Tests\TestCase;

class DomainSetupTest extends TestCase
{
    use CreatesWorkspaceDomains;
    use RefreshDatabase;

    private InMemoryDnsLookup $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = $this->fakeDns();
    }

    public function test_adding_a_domain_redirects_to_the_setup_wizard(): void
    {
        [, , $user] = $this->workspaceWithDomain();

        $response = $this->actingAs($user)->post(route('domains.store'), ['hostname' => 'links.example.test']);

        $domain = Domain::query()->where('hostname', 'links.example.test')->firstOrFail();
        $this->assertSame(DomainStatus::Pending, $domain->status);
        $response->assertRedirect(route('domains.setup', $domain));
    }

    public function test_adding_a_domain_rejects_a_hostname_that_differs_only_by_scheme_or_case(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();

        $this->actingAs($user)
            ->post(route('domains.store'), ['hostname' => 'HTTPS://'.strtoupper($domain->hostname).'/'])
            ->assertSessionHasErrors('hostname');

        $this->assertSame(1, Domain::query()->count());
    }

    public function test_txt_found_but_dns_not_pointing_marks_ownership_verified(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');
        $this->publishOwnership($domain)->pointTo($domain->hostname, '198.51.100.7');

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::OwnershipVerified, $domain->status);
        $this->assertNotNull($domain->dns_check_error);
        $this->assertNull($domain->dns_pointed_at);
        $this->assertFalse($domain->isUsable());
    }

    public function test_txt_found_and_dns_pointing_activates_the_domain(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');
        $this->publishOwnership($domain)->pointTo($domain->hostname, '203.0.113.10');

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::Active, $domain->status);
        $this->assertNotNull($domain->dns_pointed_at);
        $this->assertNull($domain->dns_check_error);
        $this->assertTrue($domain->isUsable());
    }

    public function test_cloudflare_proxied_domain_activates_after_txt_verification(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');
        $this->publishOwnership($domain)->pointTo($domain->hostname, '104.16.0.10');

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::Active, $domain->status);
        $this->assertNotNull($domain->dns_pointed_at);
        $this->assertNull($domain->dns_check_error);
    }

    public function test_cloudflare_proxy_does_not_count_as_pointing_without_ownership(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        $this->dns->pointTo($domain->hostname, '104.16.0.10');

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::Failed, $domain->status);
        $this->assertNull($domain->dns_pointed_at);
    }

    public function test_missing_txt_marks_verification_failed(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::Failed, $domain->status);
        $this->assertSame('Expected DNS TXT record was not found.', $domain->failure_reason);
    }

    public function test_active_domain_stays_active_when_rechecked_without_txt(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain(status: DomainStatus::Active);

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $domain->refresh();
        $this->assertSame(DomainStatus::Active, $domain->status);
        $this->assertNull($domain->failure_reason);
        $this->assertNull($domain->dns_check_error);
    }

    public function test_hostname_dns_target_falls_back_to_default_domain_and_uses_cname(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        app(InstanceSettings::class)->set('default_domain', 'app.example.test');
        $this->publishOwnership($domain)->pointTo($domain->hostname, '203.0.113.10');
        $this->dns->pointTo('app.example.test', '203.0.113.10');

        $this->actingAs($user)->post(route('domains.verify', $domain))->assertRedirect();

        $this->assertSame(DomainStatus::Active, $domain->fresh()->status);
    }

    public function test_setup_page_renders_with_dns_instructions(): void
    {
        [, $domain, $user] = $this->workspaceWithDomain();
        app(InstanceSettings::class)->set('dns_target', '203.0.113.10');

        $this->actingAs($user)
            ->get(route('domains.setup', $domain))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Domains/Setup')
                ->where('domain.hostname', $domain->hostname)
                ->where('domain.status', DomainStatus::Pending->value)
                ->where('domain.expected_txt_name', '_openlink.'.$domain->hostname)
                ->where('domain.expected_txt', 'openlink-verification='.$domain->verification_token)
                ->where('domain.dns_record.type', 'A')
                ->where('domain.dns_record.value', '203.0.113.10'));
    }

    private function publishOwnership(Domain $domain): InMemoryDnsLookup
    {
        return $this->dns->publishTxt($domain->verificationTxtName(), $domain->verificationTxtValue());
    }
}

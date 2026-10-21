<?php

namespace Tests\Feature\AppAssociations;

use App\Enums\DomainStatus;
use App\Models\AnalyticsEvent;
use App\Models\AppAssociation;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class AssociationFilesTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private const FINGERPRINT = '14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5';

    public function test_active_domain_publishes_apple_association_file(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->iosAssociation($domain, ['/spring', '/products/*']);

        $response = $this->get('https://go.shop.test/.well-known/apple-app-site-association');

        $response->assertOk();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame([
            'applinks' => [
                'details' => [[
                    'appIDs' => ['ABCDE12345.com.shop.app'],
                    'components' => [
                        ['/' => '/.well-known/*', 'exclude' => true],
                        ['/' => '/qr/*', 'exclude' => true],
                        ['/' => '/password/*', 'exclude' => true],
                        ['/' => '/spring'],
                        ['/' => '/products/*'],
                    ],
                ]],
            ],
        ], $response->json());
    }

    public function test_active_domain_publishes_android_asset_links_file(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->androidAssociation($domain);
        $this->androidAssociation($domain, 'com.shop.beta');

        $response = $this->get('https://go.shop.test/.well-known/assetlinks.json');

        $response->assertOk();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'com.shop.app',
                    'sha256_cert_fingerprints' => [self::FINGERPRINT],
                ],
            ],
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => 'com.shop.beta',
                    'sha256_cert_fingerprints' => [self::FINGERPRINT],
                ],
            ],
        ], $response->json());
    }

    public function test_apple_file_lists_apps_in_creation_order(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->iosAssociation($domain, ['/*'], 'com.shop.first');
        $this->iosAssociation($domain, ['/*'], 'com.shop.second');

        $details = $this->get('https://go.shop.test/.well-known/apple-app-site-association')->json('applinks.details');

        $this->assertSame(['ABCDE12345.com.shop.first'], $details[0]['appIDs']);
        $this->assertSame(['ABCDE12345.com.shop.second'], $details[1]['appIDs']);
    }

    public function test_platform_without_associations_returns_not_found_without_resolving_a_short_link(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->shortLink($workspace, $domain, 'well-known');
        $this->iosAssociation($domain, ['/*']);

        $response = $this->get('https://go.shop.test/.well-known/assetlinks.json');

        $response->assertNotFound();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame(0, AnalyticsEvent::query()->count());
    }

    public function test_unknown_hostname_returns_not_found(): void
    {
        $this->get('https://unknown.test/.well-known/apple-app-site-association')->assertNotFound();
        $this->get('https://unknown.test/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_disabled_domain_stops_publishing(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->iosAssociation($domain, ['/*']);
        $this->androidAssociation($domain);
        $domain->forceFill(['status' => DomainStatus::Disabled, 'disabled_at' => now()])->save();

        $this->get('https://go.shop.test/.well-known/apple-app-site-association')->assertNotFound();
        $this->get('https://go.shop.test/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_domain_that_is_not_active_does_not_publish(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->iosAssociation($domain, ['/*']);
        $domain->forceFill(['status' => DomainStatus::OwnershipVerified])->save();

        $this->get('https://go.shop.test/.well-known/apple-app-site-association')->assertNotFound();
    }

    public function test_default_domain_never_publishes(): void
    {
        $default = Domain::create([
            'workspace_id' => null,
            'hostname' => 'short.test',
            'status' => DomainStatus::Active,
            'is_default' => true,
            'verification_token' => 'default-token',
        ]);
        $this->iosAssociation($default, ['/*']);
        $this->androidAssociation($default);

        $this->get('https://short.test/.well-known/apple-app-site-association')->assertNotFound();
        $this->get('https://short.test/.well-known/assetlinks.json')->assertNotFound();
    }

    public function test_application_host_never_publishes(): void
    {
        config(['app.host' => 'app.openlink.test']);
        [, $domain] = $this->workspaceWithActiveDomain('app.openlink.test');
        $this->iosAssociation($domain, ['/*']);

        $this->get('https://app.openlink.test/.well-known/apple-app-site-association')->assertNotFound();
    }

    public function test_claimed_paths_keep_normal_short_link_resolution_as_web_fallback(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->iosAssociation($domain, ['/*']);
        $this->androidAssociation($domain);
        $this->shortLink($workspace, $domain, 'secret', ['password_hash' => Hash::make('opensesame')]);
        $this->shortLink($workspace, $domain, 'once', ['visit_limit' => 1]);
        $this->shortLink($workspace, $domain, 'old', ['expires_at' => now()->subDay()]);

        $this->get('https://go.shop.test/secret')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Public/Password'));
        $this->get('https://go.shop.test/once')->assertRedirect('https://example.com/landing');
        $this->get('https://go.shop.test/once')->assertNotFound();
        $this->get('https://go.shop.test/old')->assertNotFound();
    }

    private function iosAssociation(Domain $domain, array $paths, string $bundle = 'com.shop.app'): AppAssociation
    {
        return AppAssociation::create([
            'domain_id' => $domain->id,
            'platform' => AppAssociation::PLATFORM_IOS,
            'team_id' => 'ABCDE12345',
            'identifier' => $bundle,
            'paths' => $paths,
        ]);
    }

    private function androidAssociation(Domain $domain, string $package = 'com.shop.app', array $fingerprints = [self::FINGERPRINT]): AppAssociation
    {
        return AppAssociation::create([
            'domain_id' => $domain->id,
            'platform' => AppAssociation::PLATFORM_ANDROID,
            'identifier' => $package,
            'sha256_cert_fingerprints' => $fingerprints,
        ]);
    }
}

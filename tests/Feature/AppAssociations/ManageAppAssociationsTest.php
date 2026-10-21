<?php

namespace Tests\Feature\AppAssociations;

use App\Enums\DomainStatus;
use App\Models\AppAssociation;
use App\Models\Domain;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class ManageAppAssociationsTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private const FINGERPRINT = '14:6D:E9:83:C5:73:06:50:D8:EE:B9:95:2F:34:FC:64:16:A0:83:42:E6:1D:BE:A8:8A:04:96:B2:3F:CF:44:E5';

    public function test_owner_adds_an_ios_app_with_normalized_identifiers(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('domains.app-associations.store', $domain), [
                'platform' => 'ios',
                'team_id' => 'abcde12345',
                'identifier' => 'com.shop.app',
                'paths' => [' /spring ', '/products/*'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $association = AppAssociation::query()->sole();
        $this->assertSame($domain->id, $association->domain_id);
        $this->assertSame('ABCDE12345.com.shop.app', $association->appId());
        $this->assertSame(['/spring', '/products/*'], $association->paths);
    }

    public function test_admin_adds_an_android_app_with_normalized_fingerprints(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $admin = $this->memberOf($workspace, WorkspaceMember::ROLE_ADMIN);

        $this->actingInWorkspace($admin, $workspace)
            ->post(route('domains.app-associations.store', $domain), [
                'platform' => 'android',
                'identifier' => 'com.shop.app',
                'sha256_cert_fingerprints' => [strtolower(self::FINGERPRINT)],
            ])
            ->assertSessionHasNoErrors();

        $association = AppAssociation::query()->sole();
        $this->assertSame([self::FINGERPRINT], $association->sha256_cert_fingerprints);
        $this->assertNull($association->paths);
        $this->assertNull($association->team_id);
    }

    public function test_editors_and_viewers_cannot_manage_app_associations(): void
    {
        [$workspace, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        $existing = $this->association($domain);

        foreach ([WorkspaceMember::ROLE_EDITOR, WorkspaceMember::ROLE_VIEWER] as $role) {
            $member = $this->memberOf($workspace, $role);

            $this->actingInWorkspace($member, $workspace)
                ->post(route('domains.app-associations.store', $domain), $this->iosInput())
                ->assertForbidden();
            $this->actingInWorkspace($member, $workspace)
                ->delete(route('domains.app-associations.destroy', [$domain, $existing]))
                ->assertForbidden();
            $this->actingInWorkspace($member, $workspace)
                ->get(route('domains.apps', $domain))
                ->assertForbidden();
        }

        $this->assertSame(1, AppAssociation::query()->count());
    }

    public function test_another_workspace_cannot_manage_the_domain(): void
    {
        [, $domain] = $this->workspaceWithActiveDomain('go.shop.test');
        [$otherWorkspace, $otherOwner] = $this->workspaceWithOwner('Other');
        $existing = $this->association($domain);

        $this->actingInWorkspace($otherOwner, $otherWorkspace)
            ->post(route('domains.app-associations.store', $domain), $this->iosInput())
            ->assertForbidden();
        $this->actingInWorkspace($otherOwner, $otherWorkspace)
            ->delete(route('domains.app-associations.destroy', [$domain, $existing]))
            ->assertForbidden();

        $this->assertSame(1, AppAssociation::query()->count());
    }

    public function test_association_must_belong_to_the_domain_in_the_route(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');
        $second = Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => 'links.shop.test',
            'status' => DomainStatus::Active,
            'verification_token' => 'second-token',
        ]);
        $foreign = $this->association($second);

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('domains.app-associations.destroy', [$domain, $foreign]))
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_default_domain_cannot_be_claimed(): void
    {
        [$workspace, , $owner] = $this->workspaceWithActiveDomain('go.shop.test');
        $default = Domain::create([
            'workspace_id' => null,
            'hostname' => 'short.test',
            'status' => DomainStatus::Active,
            'is_default' => true,
            'verification_token' => 'default-token',
        ]);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('domains.app-associations.store', $default), $this->iosInput())
            ->assertForbidden();

        $this->assertSame(0, AppAssociation::query()->count());
    }

    public function test_application_host_cannot_be_claimed(): void
    {
        config(['app.host' => 'app.openlink.test']);
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('app.openlink.test');

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('domains.app-associations.store', $domain), $this->iosInput())
            ->assertSessionHasErrors('domain');

        $this->assertSame(0, AppAssociation::query()->count());
    }

    public function test_domain_must_be_active(): void
    {
        foreach ([DomainStatus::Pending, DomainStatus::OwnershipVerified, DomainStatus::Disabled] as $status) {
            [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go-'.$status->value.'.test');
            $domain->forceFill(['status' => $status])->save();

            $this->actingInWorkspace($owner, $workspace)
                ->post(route('domains.app-associations.store', $domain), $this->iosInput())
                ->assertSessionHasErrors('domain');
        }

        $this->assertSame(0, AppAssociation::query()->count());
    }

    public function test_malformed_identifiers_and_fingerprints_are_rejected(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');

        $cases = [
            ['input' => ['team_id' => 'SHORT'], 'error' => 'team_id'],
            ['input' => ['team_id' => 'ABCDE-1234'], 'error' => 'team_id'],
            ['input' => ['identifier' => 'noperiod'], 'error' => 'identifier'],
            ['input' => ['identifier' => 'com.shop/app'], 'error' => 'identifier'],
            ['input' => ['identifier' => '{"applinks":{}}'], 'error' => 'identifier'],
            ['input' => ['paths' => []], 'error' => 'paths'],
            ['input' => ['paths' => ['spring']], 'error' => 'paths.0'],
            ['input' => ['paths' => ['/spring?x=<script>']], 'error' => 'paths.0'],
            ['input' => ['paths' => ['/.well-known/*']], 'error' => 'paths.0'],
            ['input' => ['paths' => ['/qr/*']], 'error' => 'paths.0'],
            ['input' => ['paths' => ['/PASSWORD/1']], 'error' => 'paths.0'],
            ['input' => ['paths' => ['/%71r/*']], 'error' => 'paths.0'],
            ['input' => ['paths' => ['/qr']], 'error' => 'paths.0'],
            ['input' => ['paths' => [['/' => '/*']]], 'error' => 'paths.0'],
            ['input' => ['platform' => 'windows'], 'error' => 'platform'],
            ['input' => ['platform' => 'android', 'identifier' => '1com.shop', 'sha256_cert_fingerprints' => [self::FINGERPRINT]], 'error' => 'identifier'],
            ['input' => ['platform' => 'android', 'identifier' => 'com.shop-app', 'sha256_cert_fingerprints' => [self::FINGERPRINT]], 'error' => 'identifier'],
            ['input' => ['platform' => 'android', 'identifier' => 'com.shop.app', 'sha256_cert_fingerprints' => []], 'error' => 'sha256_cert_fingerprints'],
            ['input' => ['platform' => 'android', 'identifier' => 'com.shop.app', 'sha256_cert_fingerprints' => ['14:6D:E9']], 'error' => 'sha256_cert_fingerprints.0'],
            ['input' => ['platform' => 'android', 'identifier' => 'com.shop.app', 'sha256_cert_fingerprints' => [str_replace(':', '', self::FINGERPRINT)]], 'error' => 'sha256_cert_fingerprints.0'],
            ['input' => ['platform' => 'android', 'identifier' => 'com.shop.app', 'sha256_cert_fingerprints' => ['ZZ'.substr(self::FINGERPRINT, 2)]], 'error' => 'sha256_cert_fingerprints.0'],
        ];

        foreach ($cases as $case) {
            $this->actingInWorkspace($owner, $workspace)
                ->post(route('domains.app-associations.store', $domain), [...$this->iosInput(), ...$case['input']])
                ->assertSessionHasErrors($case['error']);
        }

        $this->assertSame(0, AppAssociation::query()->count());
    }

    public function test_the_same_app_cannot_be_added_twice_to_a_domain(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->association($domain);

        $this->actingInWorkspace($owner, $workspace)
            ->post(route('domains.app-associations.store', $domain), $this->iosInput())
            ->assertSessionHasErrors('identifier');

        $this->assertSame(1, AppAssociation::query()->count());
    }

    public function test_owner_removes_an_app_association(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');
        $association = $this->association($domain);

        $this->actingInWorkspace($owner, $workspace)
            ->delete(route('domains.app-associations.destroy', [$domain, $association]))
            ->assertRedirect();

        $this->assertModelMissing($association);
        $this->get('https://go.shop.test/.well-known/apple-app-site-association')->assertNotFound();
    }

    public function test_apps_page_shows_associations_and_generated_files(): void
    {
        [$workspace, $domain, $owner] = $this->workspaceWithActiveDomain('go.shop.test');
        $this->association($domain);

        $this->actingInWorkspace($owner, $workspace)
            ->get(route('domains.apps', $domain))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Domains/Apps')
                ->where('domain.hostname', 'go.shop.test')
                ->where('domain.can_publish', true)
                ->has('associations', 1)
                ->where('associations.0.app_id', 'ABCDE12345.com.shop.app')
                ->where('associations.0.paths', ['/*'])
                ->where('files.apple.url', 'https://go.shop.test/.well-known/apple-app-site-association')
                ->where('files.apple.content.applinks.details.0.appIDs', ['ABCDE12345.com.shop.app'])
                ->where('files.android.url', 'https://go.shop.test/.well-known/assetlinks.json')
                ->where('files.android.content', null)
                ->where('reserved_paths', ['/.well-known/*', '/qr/*', '/password/*']));
    }

    private function iosInput(): array
    {
        return [
            'platform' => 'ios',
            'team_id' => 'ABCDE12345',
            'identifier' => 'com.shop.app',
            'paths' => ['/*'],
        ];
    }

    private function association(Domain $domain): AppAssociation
    {
        return AppAssociation::create([
            'domain_id' => $domain->id,
            'platform' => AppAssociation::PLATFORM_IOS,
            'team_id' => 'ABCDE12345',
            'identifier' => 'com.shop.app',
            'paths' => ['/*'],
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AnalyticsEvent;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\InviteLink;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Analytics\Outcome;
use App\Services\SlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OpenlinkMvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_registration_creates_instance_admin_then_requires_email_verification_before_onboarding(): void
    {
        $this->post('/register', [
            'name' => 'Bear',
            'email' => 'bear@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'bear@example.com')->firstOrFail();

        $this->assertTrue($user->is_instance_admin);
        $this->assertDatabaseHas('domains', ['hostname' => 'localhost', 'status' => Domain::STATUS_ACTIVE, 'is_default' => true]);
        $this->assertDatabaseCount('workspaces', 0);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice', absolute: false));

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('onboarding.show', absolute: false));

        $this->actingAs($user)->post(route('onboarding.workspace'), ['name' => 'Bear Co'])
            ->assertRedirect(route('onboarding.show', absolute: false));

        $this->assertDatabaseHas('workspaces', ['name' => 'Bear Co', 'owner_id' => $user->id]);
        $this->assertDatabaseHas('workspace_members', ['user_id' => $user->id, 'role' => WorkspaceMember::ROLE_OWNER]);
    }

    public function test_slug_service_rejects_reserved_slugs(): void
    {
        [, $domain] = $this->workspaceAndDomain();
        $slugs = app(SlugService::class);

        $this->expectException(ValidationException::class);
        $slugs->validateCustom($domain, 'dashboard');
    }

    public function test_slug_service_allows_application_reserved_slugs_on_redirect_only_domains(): void
    {
        [, $domain] = $this->workspaceAndDomain('go.example.test');
        $slugs = app(SlugService::class);

        $this->assertSame('dashboard', $slugs->validateCustom($domain, 'dashboard'));
        $this->assertSame('app/release', $slugs->validateCustom($domain, 'app/release'));
    }

    public function test_slug_service_rejects_duplicate_slugs_per_domain(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $slugs = app(SlugService::class);

        ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'event/vip',
            'destination_url' => 'https://example.com',
        ]);

        $this->expectException(ValidationException::class);
        $slugs->validateCustom($domain, 'event/vip');
    }

    public function test_public_resolution_redirects_and_counts_successful_visits(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'hello',
            'destination_url' => 'https://example.com/landing',
        ]);

        $this->withHeader('Host', 'localhost')
            ->get('/hello')
            ->assertRedirect('https://example.com/landing');

        $this->assertSame(1, $link->fresh()->successful_visits);
        $this->assertDatabaseHas('analytics_events', [
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'outcome' => Outcome::SUCCESS,
        ]);
    }

    public function test_application_routes_render_only_on_application_domain(): void
    {
        [, $domain] = $this->workspaceAndDomain('go.example.test');

        $this->get('http://localhost/login')
            ->assertOk();

        $this->get('http://'.$domain->hostname.'/login')
            ->assertStatus(404);
    }

    public function test_redirect_only_domain_resolves_application_route_names_as_slugs(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain('go.example.test');
        ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'login',
            'destination_url' => 'https://example.com/customer-login',
        ]);

        $this->get('http://'.$domain->hostname.'/login')
            ->assertRedirect('https://example.com/customer-login');
    }

    public function test_redirect_only_domain_root_shows_neutral_unavailable_page(): void
    {
        [, $domain] = $this->workspaceAndDomain('go.example.test');

        $this->get('http://'.$domain->hostname.'/')
            ->assertStatus(404);
    }

    public function test_redirect_only_domain_resolves_reserved_prefixes_as_slugs(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain('go.example.test');
        ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'app/release',
            'destination_url' => 'https://example.com/releases',
        ]);

        $this->get('http://'.$domain->hostname.'/app/release')
            ->assertRedirect('https://example.com/releases');
    }

    public function test_redirect_only_domain_does_not_render_authenticated_ui_routes(): void
    {
        [, $domain, $user] = $this->workspaceAndDomain('go.example.test');

        foreach (['dashboard', 'links', 'domains', 'members', 'workspaces', 'settings', 'profile'] as $path) {
            $this->actingAs($user)
                ->get('http://'.$domain->hostname.'/'.$path)
                ->assertStatus(404);
        }
    }

    public function test_protected_link_password_flow_works_on_redirect_only_domain(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain('go.example.test');
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'secret',
            'destination_url' => 'https://example.com/secret',
            'password_hash' => Hash::make('opensesame'),
        ]);

        $this->get('http://'.$domain->hostname.'/secret')
            ->assertOk();

        $this->post('http://'.$domain->hostname.'/password/'.$link->id, ['password' => 'opensesame'])
            ->assertRedirect('https://example.com/secret');
    }

    public function test_protected_link_password_form_posts_to_current_host(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain('go.example.test');
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'host-bound-secret',
            'destination_url' => 'https://example.com/host-bound-secret',
            'password_hash' => Hash::make('opensesame'),
        ]);

        $this->get('http://'.$domain->hostname.'/host-bound-secret')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Password')
                ->where('passwordUrl', '/password/'.$link->id));
    }

    public function test_visit_limit_only_counts_successful_destination_redirects(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'limited',
            'destination_url' => 'https://example.com/limited',
            'visit_limit' => 1,
        ]);

        $this->withHeader('Host', 'localhost')->get('/limited')->assertRedirect('https://example.com/limited');
        $this->withHeader('Host', 'localhost')->get('/limited')->assertStatus(404);

        $this->assertSame(1, $link->fresh()->successful_visits);
        $this->assertDatabaseHas('analytics_events', [
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'outcome' => Outcome::VISIT_LIMIT_REACHED,
        ]);
    }

    public function test_protected_link_requires_password_before_visit_is_counted(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'secret',
            'destination_url' => 'https://example.com/secret',
            'password_hash' => Hash::make('opensesame'),
        ]);

        $this->withHeader('Host', 'localhost')->get('/secret')->assertOk();
        $this->assertSame(0, $link->fresh()->successful_visits);

        $this->post(route('public.password', $link), ['password' => 'wrong'])->assertStatus(403);
        $this->assertSame(0, $link->fresh()->successful_visits);

        $this->post(route('public.password', $link), ['password' => 'opensesame'])
            ->assertRedirect('https://example.com/secret');
        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_inertia_password_submit_returns_location_for_external_redirect(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'secret-inertia',
            'destination_url' => 'https://example.com/secret-inertia',
            'password_hash' => Hash::make('opensesame'),
        ]);

        $this->withHeader('X-Inertia', 'true')
            ->post(route('public.password', $link), ['password' => 'opensesame'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://example.com/secret-inertia');

        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    public function test_qr_code_export_returns_svg(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'poster',
            'destination_url' => 'https://example.com/poster',
        ]);
        $qrCode = $link->qrCodes()->create([
            'name' => 'Poster',
            'token' => 'qr-token',
        ]);

        $this->actingAs($user)
            ->get(route('qr-codes.export', [$qrCode, 'svg']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_qr_code_preview_returns_inline_svg(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'badge',
            'destination_url' => 'https://example.com/badge',
        ]);
        $qrCode = $link->qrCodes()->create([
            'name' => 'Badge',
            'token' => 'badge-qr-token',
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->get(route('qr-codes.preview', $qrCode))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'inline; filename="'.$qrCode->token.'.svg"');
    }

    public function test_two_factor_code_is_required_when_enabled(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->create([
            'email' => 'two@example.com',
            'password' => Hash::make('password'),
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login.two-factor', absolute: false));

        $this->assertGuest();

        $this->post(route('login.two-factor'), [
            'one_time_password' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invite_link_allows_registration_in_invite_only_mode(): void
    {
        [$workspace, , $owner] = $this->workspaceAndDomain();

        $this->actingAs($owner)->post(route('invite-links.store'), [
            'role' => WorkspaceMember::ROLE_EDITOR,
        ])->assertRedirect();

        $link = InviteLink::query()->where('workspace_id', $workspace->id)->firstOrFail();
        $this->post('/logout');

        $this->get(route('join.show', $link))->assertOk();
        $this->get(route('register', ['invite' => $link->token]))->assertOk();

        $this->post('/register', [
            'name' => 'Invited Editor',
            'email' => 'editor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite_token' => $link->token,
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'editor@example.com')->firstOrFail();
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $this->assertSame(1, $link->fresh()->uses);
    }

    public function test_editor_cannot_create_link_inside_folder_without_edit_permission(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $editor = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $editor->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $folder = Folder::create([
            'workspace_id' => $workspace->id,
            'name' => 'Private Campaign',
        ]);

        $this->actingAs($editor)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('short-links.store'), [
                'domain_id' => $domain->id,
                'folder_id' => $folder->id,
                'slug' => 'private',
                'destination_url' => 'https://example.com/private',
            ])->assertForbidden();

        FolderPermission::create([
            'folder_id' => $folder->id,
            'user_id' => $editor->id,
            'permission' => FolderPermission::CAN_EDIT,
        ]);

        $this->actingAs($editor)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('short-links.store'), [
                'domain_id' => $domain->id,
                'folder_id' => $folder->id,
                'slug' => 'private',
                'destination_url' => 'https://example.com/private',
            ])->assertRedirect();

        $this->assertDatabaseHas('short_links', [
            'folder_id' => $folder->id,
            'slug' => 'private',
        ]);
    }

    public function test_analytics_retention_command_prunes_old_events_only(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'old',
            'destination_url' => 'https://example.com/old',
        ]);
        $base = [
            'workspace_id' => $workspace->id,
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'outcome' => Outcome::SUCCESS,
        ];
        AnalyticsEvent::create([...$base, 'occurred_at' => now()->subDays(400)]);
        AnalyticsEvent::create([...$base, 'occurred_at' => now()->subDays(10)]);

        $this->artisan('openlink:prune-analytics')->assertSuccessful();

        $this->assertSame(1, AnalyticsEvent::query()->count());
        $this->assertTrue(AnalyticsEvent::query()->sole()->occurred_at->greaterThan(now()->subDays(365)));
    }

    public function test_workspace_manager_can_delete_workspace_domain(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->delete(route('domains.destroy', $domain))
            ->assertRedirect();

        $this->assertDatabaseMissing('domains', ['id' => $domain->id]);
    }

    public function test_default_domain_cannot_be_deleted_from_workspace_domains(): void
    {
        [$workspace, , $user] = $this->workspaceAndDomain();
        $defaultDomain = Domain::create([
            'workspace_id' => null,
            'hostname' => 'short.test',
            'status' => Domain::STATUS_ACTIVE,
            'is_default' => true,
            'verification_token' => 'default-token',
            'verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->delete(route('domains.destroy', $defaultDomain))
            ->assertForbidden();

        $this->assertDatabaseHas('domains', ['id' => $defaultDomain->id]);
    }

    public function test_workspace_manager_can_rename_and_delete_folder_leaving_links_unfiled(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'folder_id' => $folder->id,
            'domain_id' => $domain->id,
            'slug' => 'filed',
            'destination_url' => 'https://example.com/filed',
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('folders.update', $folder), ['name' => 'Renamed Campaign'])
            ->assertRedirect();

        $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Renamed Campaign']);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->delete(route('folders.destroy', $folder))
            ->assertRedirect();

        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseHas('short_links', ['id' => $link->id, 'folder_id' => null]);
    }

    public function test_viewer_cannot_rename_or_delete_folder(): void
    {
        [$workspace] = $this->workspaceAndDomain();
        $viewer = User::factory()->create();
        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => WorkspaceMember::ROLE_VIEWER,
        ]);
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);

        $this->actingAs($viewer)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('folders.update', $folder), ['name' => 'Hijacked'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->withSession(['workspace_id' => $workspace->id])
            ->delete(route('folders.destroy', $folder))
            ->assertForbidden();

        $this->assertDatabaseHas('folders', ['id' => $folder->id, 'name' => 'Campaign']);
    }

    public function test_short_link_can_be_moved_between_folders_but_not_across_workspaces(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => 'Campaign']);
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'movable',
            'destination_url' => 'https://example.com/movable',
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('short-links.move', $link), ['folder_id' => $folder->id])
            ->assertRedirect();

        $this->assertDatabaseHas('short_links', ['id' => $link->id, 'folder_id' => $folder->id]);

        $otherOwner = User::factory()->create();
        $otherWorkspace = Workspace::create([
            'owner_id' => $otherOwner->id,
            'name' => 'Other',
            'slug' => 'other',
            'settings' => [],
        ]);
        $foreignFolder = Folder::create(['workspace_id' => $otherWorkspace->id, 'name' => 'Foreign']);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('short-links.move', $link), ['folder_id' => $foreignFolder->id])
            ->assertSessionHasErrors('folder_id');

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('short-links.move', $link), ['folder_id' => null])
            ->assertRedirect();

        $this->assertDatabaseHas('short_links', ['id' => $link->id, 'folder_id' => null]);
    }

    public function test_workspace_manager_can_delete_current_workspace_and_switch_to_another(): void
    {
        [$workspace, , $user] = $this->workspaceAndDomain();
        $otherWorkspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Other',
            'slug' => 'other',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_OWNER,
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->delete(route('workspaces.destroy', $workspace))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertSame($otherWorkspace->id, session('workspace_id'));
    }

    public function test_domain_can_transfer_to_another_managed_workspace_when_unused(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        $otherWorkspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Other',
            'slug' => 'other',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_ADMIN,
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('domains.transfer', $domain), ['workspace_id' => $otherWorkspace->id])
            ->assertRedirect();

        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'workspace_id' => $otherWorkspace->id,
        ]);
    }

    public function test_domain_transfer_is_blocked_when_domain_has_links(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        $otherWorkspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => 'Other',
            'slug' => 'other',
            'settings' => [],
        ]);
        WorkspaceMember::create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_ADMIN,
        ]);
        ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'kept',
            'destination_url' => 'https://example.com/kept',
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->post(route('domains.transfer', $domain), ['workspace_id' => $otherWorkspace->id])
            ->assertSessionHasErrors('workspace_id');

        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_link_password_can_be_removed_by_submitting_empty_password(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'public-again',
            'destination_url' => 'https://example.com/public-again',
            'password_hash' => Hash::make('secret'),
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('short-links.update', $link), [
                'folder_id' => null,
                'destination_url' => 'https://example.com/public-again',
                'fallback_url' => null,
                'is_enabled' => true,
                'activates_at' => null,
                'expires_at' => null,
                'visit_limit' => null,
                'password' => null,
            ])->assertRedirect();

        $this->assertNull($link->fresh()->password_hash);
    }

    public function test_short_url_can_be_changed_and_old_address_stops_resolving(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'before',
            'destination_url' => 'https://example.com/target',
        ]);

        // Warm the resolution cache for the old address.
        $this->get('/before')->assertRedirect('https://example.com/target');

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('short-links.update', $link), [
                'folder_id' => null,
                'domain_id' => $domain->id,
                'slug' => 'after',
                'destination_url' => 'https://example.com/target',
                'fallback_url' => null,
                'is_enabled' => true,
                'activates_at' => null,
                'expires_at' => null,
                'visit_limit' => null,
            ])->assertRedirect();

        $this->assertSame('after', $link->fresh()->slug);

        // The cached entry for the old address must be gone immediately.
        $this->get('/before')->assertNotFound();
        $this->get('/after')->assertRedirect('https://example.com/target');
    }

    public function test_short_url_change_rejects_slug_already_taken(): void
    {
        [$workspace, $domain, $user] = $this->workspaceAndDomain();
        ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'taken',
            'destination_url' => 'https://example.com/taken',
        ]);
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'mine',
            'destination_url' => 'https://example.com/mine',
        ]);

        $this->actingAs($user)
            ->withSession(['workspace_id' => $workspace->id])
            ->patch(route('short-links.update', $link), [
                'folder_id' => null,
                'domain_id' => $domain->id,
                'slug' => 'taken',
                'destination_url' => 'https://example.com/mine',
                'fallback_url' => null,
                'is_enabled' => true,
                'activates_at' => null,
                'expires_at' => null,
                'visit_limit' => null,
            ])->assertSessionHasErrors('slug');

        $this->assertSame('mine', $link->fresh()->slug);
    }

    public function test_password_submit_resolves_known_link_even_when_post_host_differs(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'secret-custom',
            'destination_url' => 'https://example.com/secret-custom',
            'password_hash' => Hash::make('opensesame'),
        ]);

        $this->withHeader('Host', 'app.test')
            ->post(route('public.password', $link), ['password' => 'opensesame'])
            ->assertRedirect('https://example.com/secret-custom');

        $this->assertSame(1, $link->fresh()->successful_visits);
    }

    /** @return array{Workspace, Domain, User} */
    private function workspaceAndDomain(string $hostname = 'localhost'): array
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
            'status' => Domain::STATUS_ACTIVE,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);

        return [$workspace, $domain, $user];
    }
}

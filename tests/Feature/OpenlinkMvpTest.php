<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\AnalyticsDailyAggregate;
use App\Models\Folder;
use App\Models\FolderPermission;
use App\Models\Invitation;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\AnalyticsService;
use App\Services\SlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class OpenlinkMvpTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_registration_creates_instance_admin_workspace_and_default_domain(): void
    {
        $this->post('/register', [
            'name' => 'Bear',
            'email' => 'bear@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'bear@example.com')->firstOrFail();

        $this->assertTrue($user->is_instance_admin);
        $this->assertDatabaseHas('workspaces', ['name' => 'Personal', 'owner_id' => $user->id]);
        $this->assertDatabaseHas('workspace_members', ['user_id' => $user->id, 'role' => WorkspaceMember::ROLE_OWNER]);
        $this->assertDatabaseHas('domains', ['hostname' => 'localhost', 'status' => Domain::STATUS_VERIFIED, 'is_default' => true]);
    }

    public function test_slug_service_rejects_reserved_slugs(): void
    {
        [, $domain] = $this->workspaceAndDomain();
        $slugs = app(SlugService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $slugs->validateCustom($domain, 'dashboard');
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

        $this->expectException(\Illuminate\Validation\ValidationException::class);
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
        $this->assertDatabaseHas('analytics_totals', [
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'outcome' => AnalyticsService::OUTCOME_SUCCESS,
            'count' => 1,
        ]);
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
        $this->assertDatabaseHas('analytics_totals', [
            'short_link_id' => $link->id,
            'metric' => 'visit',
            'outcome' => AnalyticsService::OUTCOME_VISIT_LIMIT_REACHED,
            'count' => 1,
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
        $secret = (new Google2FA())->generateSecretKey();
        $user = User::factory()->create([
            'email' => 'two@example.com',
            'password' => Hash::make('password'),
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('one_time_password');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'one_time_password' => (new Google2FA())->getCurrentOtp($secret),
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_invitation_token_allows_registration_in_invite_only_mode(): void
    {
        [$workspace, , $owner] = $this->workspaceAndDomain();
        Notification::fake();

        $this->actingAs($owner)->post(route('invitations.store'), [
            'email' => 'editor@example.com',
            'role' => WorkspaceMember::ROLE_EDITOR,
        ])->assertRedirect();

        $invitation = Invitation::query()->where('email', 'editor@example.com')->firstOrFail();
        $this->get(route('invitations.show', $invitation))->assertOk();
        $this->post('/logout');

        $this->post('/register', [
            'name' => 'Invited Editor',
            'email' => 'editor@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_token' => $invitation->token,
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'editor@example.com')->firstOrFail();
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
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

    public function test_analytics_retention_command_prunes_daily_aggregates_only(): void
    {
        [$workspace, $domain] = $this->workspaceAndDomain();
        $link = ShortLink::create([
            'workspace_id' => $workspace->id,
            'domain_id' => $domain->id,
            'slug' => 'old',
            'destination_url' => 'https://example.com/old',
        ]);
        AnalyticsDailyAggregate::create([
            'workspace_id' => $workspace->id,
            'short_link_id' => $link->id,
            'date' => now()->subDays(400)->toDateString(),
            'metric' => 'visit',
            'outcome' => 'success',
            'device_type' => 'desktop',
            'browser' => 'Other',
            'os' => 'Other',
            'count' => 1,
        ]);

        $this->artisan('openlink:prune-analytics')->assertSuccessful();

        $this->assertDatabaseMissing('analytics_daily_aggregates', [
            'short_link_id' => $link->id,
            'date' => now()->subDays(400)->toDateString(),
        ]);
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
            'status' => Domain::STATUS_VERIFIED,
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

    /** @return array{Workspace, Domain, User} */
    private function workspaceAndDomain(): array
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
            'hostname' => 'localhost',
            'status' => Domain::STATUS_VERIFIED,
            'verification_token' => 'test-token-'.str()->random(12),
            'verified_at' => now(),
        ]);

        return [$workspace, $domain, $user];
    }
}

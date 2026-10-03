<?php

namespace Tests\Feature\Auth\OAuth;

use App\Actions\InviteLinks\JoinWorkspaceViaInviteLink;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\User;
use App\Models\WorkspaceMember;
use App\Services\InstanceSettings;
use App\Services\OAuth\OAuthProfile;
use App\Services\OAuth\OAuthSignIn;
use App\Services\Registration\AccountRegistration;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Tests\Support\CreatesWorkspaces;
use Tests\Support\FakesOAuthProviders;
use Tests\TestCase;

class OAuthInviteLinkTest extends TestCase
{
    use CreatesWorkspaces;
    use FakesOAuthProviders;
    use RefreshDatabase;

    public function test_oauth_creates_a_user_and_joins_workspace_through_valid_invite_link(): void
    {
        $this->configureGoogle();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $inviteLink = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR);
        $this->mockSocialiteUser('google', [
            'id' => 'google-4',
            'email' => 'invited@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback(['invite_token' => $inviteLink->token])
            ->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'invited@example.com')->firstOrFail();
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_EDITOR,
        ]);
        $this->assertSame(1, $inviteLink->fresh()->uses);
    }

    public function test_oauth_invite_link_cannot_create_a_user_when_registration_is_closed(): void
    {
        $this->configureGoogle();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $inviteLink = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_EDITOR);
        app(InstanceSettings::class)->set('registration_mode', 'closed');
        $this->mockSocialiteUser('google', [
            'id' => 'google-closed',
            'email' => 'closed@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback(['invite_token' => $inviteLink->token])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Registration is not available. Use an invite link or sign in with an existing account.');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'closed@example.com']);
        $this->assertSame(0, $inviteLink->fresh()->uses);
    }

    public function test_oauth_existing_user_joins_workspace_through_valid_invite_link(): void
    {
        $this->configureGoogle();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $inviteLink = $this->inviteLink($workspace, $owner, WorkspaceMember::ROLE_VIEWER);
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $this->mockSocialiteUser('google', [
            'id' => 'google-5',
            'email' => 'existing@example.com',
            'email_verified' => true,
        ]);

        $this->googleCallback(['invite_token' => $inviteLink->token])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => WorkspaceMember::ROLE_VIEWER,
        ]);
    }

    public function test_oauth_does_not_dispatch_registered_when_invite_join_rolls_back_creation(): void
    {
        Event::fake([Registered::class]);
        [$workspace, $owner] = $this->workspaceWithOwner();
        $inviteLink = $this->inviteLink($workspace, $owner);
        $joiner = Mockery::mock(JoinWorkspaceViaInviteLink::class);
        $joiner->shouldReceive('handle')->once()->andThrow(new RuntimeException('Invite join failed'));
        $signIn = new OAuthSignIn($joiner, app(AccountRegistration::class), app(CurrentWorkspace::class));

        try {
            $signIn->userFor(new OAuthProfile(
                provider: 'google',
                providerUserId: 'rollback-test',
                email: 'rollback@example.com',
                emailVerified: true,
                name: 'Rollback User',
                avatarUrl: null,
            ), ['invite_token' => $inviteLink->token]);
            $this->fail('Invite join failure must roll back OAuth registration.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Invite join failed', $exception->getMessage());
        }

        app()->terminate();
        Event::assertNotDispatched(Registered::class);
        $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
        $this->assertDatabaseMissing('social_accounts', ['provider_user_id' => 'rollback-test']);
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class RegistrationModesTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    public static function modes(): array
    {
        return [
            'public' => ['open', true, true],
            'invite link only' => ['invite_only', false, true],
            'private' => ['closed', false, false],
        ];
    }

    #[DataProvider('modes')]
    public function test_instance_admin_can_change_registration_mode_and_guest_access_updates(string $mode, bool $public, bool $invited): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_instance_admin' => true]);
        $workspace = $this->workspaceFor($admin);
        $settings = app(InstanceSettings::class);

        $this->actingInWorkspace($admin, $workspace)
            ->patch(route('instance-settings.update'), [
                ...$settings->all(),
                'registration_mode' => $mode,
                'reserved_slugs' => implode("\n", $settings->get('reserved_slugs')),
                'reserved_prefixes' => implode("\n", $settings->get('reserved_prefixes')),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($mode, $settings->get('registration_mode'));
        $this->post(route('logout'));

        $this->get(route('login'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canRegister', $public));

        $this->get(route('register'))->assertOk()
            ->assertInertia(fn (Assert $page) => $public
                ? $page->component('Auth/Register')
                : $page->component('Auth/RegistrationUnavailable')->where('mode', $mode));

        $response = $this->post(route('register'), $this->registrationFields());

        if ($public) {
            $response->assertRedirect(route('dashboard', absolute: false));
            $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'is_instance_admin' => false]);
        } else {
            $response->assertForbidden();
            $this->assertGuest();
            $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        }
    }

    #[DataProvider('modes')]
    public function test_valid_invite_respects_registration_mode(string $mode, bool $public, bool $invited): void
    {
        Notification::fake();
        [$workspace, $owner] = $this->workspaceWithOwner();
        $invite = $this->inviteLink($workspace, $owner);
        app(InstanceSettings::class)->set('registration_mode', $mode);

        $this->get(route('register', ['invite' => $invite->token]))->assertOk()
            ->assertInertia(fn (Assert $page) => $invited
                ? $page->component('Auth/Register')->where('invite.token', $invite->token)
                : $page->component('Auth/RegistrationUnavailable')->where('mode', 'closed')->missing('invite'));

        $response = $this->post(route('register'), [
            ...$this->registrationFields(),
            'invite_token' => $invite->token,
        ]);

        if ($invited) {
            $response->assertRedirect(route('dashboard', absolute: false));
            $user = User::query()->where('email', 'new@example.com')->sole();
            $this->assertDatabaseHas('workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $user->id]);
            $this->assertSame(1, $invite->fresh()->uses);
        } else {
            $response->assertForbidden();
            $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
            $this->assertSame(0, $invite->fresh()->uses);
        }
    }

    public function test_unusable_invites_do_not_allow_registration(): void
    {
        [$workspace, $owner] = $this->workspaceWithOwner();

        foreach ([['revoked_at' => now()], ['expires_at' => now()->subMinute()], ['max_uses' => 1, 'uses' => 1]] as $attributes) {
            $invite = $this->inviteLink($workspace, $owner);
            $invite->forceFill($attributes)->save();

            $this->get(route('register', ['invite' => $invite->token]))->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Auth/RegistrationUnavailable')->where('mode', 'invite_only'));
            $this->post(route('register'), [...$this->registrationFields(), 'invite_token' => $invite->token])
                ->assertGone();
        }

        $this->get(route('register', ['invite' => 'unknown']))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/RegistrationUnavailable'));
        $this->post(route('register'), [...$this->registrationFields(), 'invite_token' => 'unknown'])
            ->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    }

    public function test_private_instance_keeps_existing_password_sign_in_available(): void
    {
        $user = User::factory()->create();
        app(InstanceSettings::class)->set('registration_mode', 'closed');

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_offers_first_account_setup_even_on_a_private_instance(): void
    {
        app(InstanceSettings::class)->set('registration_mode', 'closed');

        $this->get(route('login'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canRegister', true));
        $this->get(route('register'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    private function registrationFields(): array
    {
        return [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }
}

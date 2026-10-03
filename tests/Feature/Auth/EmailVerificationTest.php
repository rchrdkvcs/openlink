<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_verification_email_can_be_resent(): void
    {
        $user = User::factory()->unverified()->create();

        Notification::fake();

        $response = $this->actingAs($user)->post(route('verification.send'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'verification-link-sent');
    }

    public function test_verification_email_resend_handles_mail_transport_failures(): void
    {
        $user = User::factory()->unverified()->create();

        Event::listen(MessageSending::class, function (): void {
            throw new TransportException('SMTP rejected the message.');
        });

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect()
            ->assertSessionHasErrors('email');
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertInertiaFlash('emailVerified', true);
    }

    public function test_verification_confirmation_follows_the_intended_page_and_is_only_shown_once(): void
    {
        $user = User::factory()->unverified()->create();
        $intendedUrl = route('profile.edit');
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->withSession(['url.intended' => $intendedUrl])
            ->get($verificationUrl)
            ->assertRedirect($intendedUrl);

        $this->get($intendedUrl)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->hasFlash('emailVerified', true));

        $this->get($intendedUrl)
            ->assertInertia(fn (AssertableInertia $page) => $page->missingFlash('emailVerified'));
    }

    public function test_verification_confirmation_survives_the_onboarding_redirect(): void
    {
        $user = User::factory()->unverified()->create();
        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($verificationUrl)->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect(route('onboarding.show'));
        $this->get(route('onboarding.show'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Onboarding/Index')
                ->hasFlash('emailVerified', true));
    }

    public function test_already_verified_email_is_confirmed_without_dispatching_another_event(): void
    {
        $user = User::factory()->create();

        Event::fake([Verified::class]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertRedirect('/dashboard')
            ->assertInertiaFlash('emailVerified', true);

        Event::assertNotDispatched(Verified::class);
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl)
            ->assertForbidden()
            ->assertInertiaFlashMissing('emailVerified');

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}

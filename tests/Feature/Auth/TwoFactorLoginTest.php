<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

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
}

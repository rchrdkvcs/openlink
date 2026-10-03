<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_credentials_can_be_exchanged_for_a_token(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $response = $this->requestToken($user, 'secret-password');

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $token = $response->json('token');

        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->requestToken($user, 'wrong-password')->assertUnprocessable();
    }

    public function test_token_issuance_requires_verified_email(): void
    {
        app(InstanceSettings::class)->set('require_email_verification', true);
        $user = User::factory()->unverified()->create(['password' => 'secret-password']);

        $this->requestToken($user, 'secret-password')->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_token_issuance_requires_two_factor_code_when_enabled(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => 'secret-password',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $this->requestToken($user, 'secret-password')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('one_time_password');

        $this->requestToken($user, 'secret-password', ['one_time_password' => $google2fa->getCurrentOtp($secret)])
            ->assertCreated();
    }

    public function test_current_token_can_be_revoked(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $token = $this->requestToken($user, 'secret-password')->json('token');

        $this->deleteJson('/api/v1/auth/token', [], ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    private function requestToken(User $user, string $password, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/token', [
            'email' => $user->email,
            'password' => $password,
            'device_name' => 'browser-extension',
            ...$extra,
        ]);
    }
}

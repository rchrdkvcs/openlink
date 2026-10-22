<?php

namespace Tests\Feature\Diagnostics;

use App\Mail\InstanceDiagnosticsTestMail;
use App\Models\DiagnosticObservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesWorkspaces;
use Tests\TestCase;

class InstanceDiagnosticsMailTest extends TestCase
{
    use CreatesWorkspaces;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_instance_admin' => true, 'email' => 'admin@example.test']);
        $this->workspaceFor($this->admin);
        $this->actingAs($this->admin)->withHeader('Host', 'localhost');
        config(['mail.from.address' => 'openlink@example.test']);
    }

    public function test_log_mailer_is_not_configured_and_cannot_send_a_test_email(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.mail.state', 'not_configured')
                ->where('canSendTestMail', false)
                ->where('mailTest', null));

        $this->post(route('instance-diagnostics.test-mail'))->assertSessionHasErrors('mail');
        Mail::assertNothingSent();
    }

    public function test_smtp_without_a_host_is_not_configured(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => null, 'mail.mailers.smtp.url' => null]);

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('checks.mail.state', 'not_configured')
                ->where('canSendTestMail', false));
    }

    public function test_default_smtp_host_is_unset(): void
    {
        $config = require config_path('mail.php');

        $this->assertSame(env('MAIL_HOST'), $config['mailers']['smtp']['host']);
    }

    public function test_test_send_uses_a_bounded_transport_timeout(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.timeout' => null]);
        Mail::fake();

        $this->post(route('instance-diagnostics.test-mail'))->assertRedirect();

        $this->assertSame(10, config('mail.mailers.smtp.timeout'));
    }

    public function test_incomplete_transport_settings_are_not_configured(): void
    {
        config(['mail.default' => 'cloudflare', 'services.cloudflare.account_id' => null, 'services.cloudflare.key' => null]);

        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page->where('checks.mail.state', 'not_configured'));
    }

    public function test_configuration_is_reported_separately_from_an_accepted_test_send(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test', 'mail.mailers.smtp.password' => 'smtp-secret']);
        Mail::fake();

        $this->get(route('settings.diagnostics'))
            ->assertDontSee('smtp-secret')
            ->assertDontSee('smtp.example.test')
            ->assertInertia(fn ($page) => $page
                ->where('checks.mail.state', 'healthy')
                ->where('checks.mail.summary', fn (string $summary) => str_contains($summary, 'does not prove'))
                ->where('canSendTestMail', true)
                ->where('mailTest', null));

        $this->post(route('instance-diagnostics.test-mail'))->assertSessionHasNoErrors()->assertRedirect();

        Mail::assertSent(InstanceDiagnosticsTestMail::class, fn ($mail) => $mail->hasTo('admin@example.test'));
        $this->get(route('settings.diagnostics'))
            ->assertInertia(fn ($page) => $page
                ->where('mailTest.state', 'healthy')
                ->where('mailTest.summary', fn (string $summary) => str_contains($summary, 'does not confirm delivery')));
    }

    public function test_failed_test_send_is_reported_without_transport_details(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.password' => 'smtp-secret',
            'mail.mailers.smtp.timeout' => 2,
        ]);

        $this->post(route('instance-diagnostics.test-mail'))->assertSessionHasErrors('mail');

        $this->assertSame('unavailable', DiagnosticObservation::findOrFail(DiagnosticObservation::MAIL_TEST)->state);
        $this->get(route('settings.diagnostics'))
            ->assertDontSee('smtp-secret')
            ->assertDontSee('127.0.0.1')
            ->assertInertia(fn ($page) => $page
                ->where('checks.mail.state', 'healthy')
                ->where('mailTest.state', 'unavailable'));
    }

    public function test_test_emails_are_rate_limited(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test']);
        Mail::fake();

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('instance-diagnostics.test-mail'))->assertRedirect();
        }

        $this->post(route('instance-diagnostics.test-mail'))->assertTooManyRequests();
        Mail::assertSentCount(3);
    }
}

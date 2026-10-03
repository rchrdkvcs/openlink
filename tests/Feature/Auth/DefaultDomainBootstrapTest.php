<?php

namespace Tests\Feature\Auth;

use App\Services\Dns\DomainDnsTarget;
use App\Services\InstanceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DefaultDomainBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_registration_records_the_default_domain_setting_from_the_application_url(): void
    {
        Notification::fake();
        config(['app.url' => 'https://Links.Example.test']);

        $this->post('/register', [
            'name' => 'First Admin',
            'email' => 'first@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('domains', ['hostname' => 'links.example.test', 'is_default' => true]);
        $this->assertSame('links.example.test', app(InstanceSettings::class)->get('default_domain'));
        $this->assertSame('links.example.test', app(DomainDnsTarget::class)->value());
    }
}

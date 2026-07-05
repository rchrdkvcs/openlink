<?php

namespace Tests\Unit;

use App\Services\ApplicationHost;
use Tests\TestCase;

class ApplicationHostTest extends TestCase
{
    public function test_it_normalizes_configured_application_host(): void
    {
        config(['app.host' => 'HTTPS://App.Example.test:8443']);

        $host = new ApplicationHost;

        $this->assertSame('app.example.test', $host->host());
        $this->assertTrue($host->isApplicationHost('APP.EXAMPLE.TEST'));
        $this->assertFalse($host->isApplicationHost('go.example.test'));
    }

    public function test_it_falls_back_to_application_url(): void
    {
        config([
            'app.host' => null,
            'app.url' => 'https://Shorty.Example.test/app',
        ]);

        $this->assertSame('shorty.example.test', (new ApplicationHost)->host());
    }
}

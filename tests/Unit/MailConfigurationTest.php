<?php

namespace Tests\Unit;

use Tests\TestCase;

class MailConfigurationTest extends TestCase
{
    public function test_smtp_mailer_has_a_request_safe_timeout(): void
    {
        $this->assertSame(10, config('mail.mailers.smtp.timeout'));
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationMessagesTest extends TestCase
{
    public function test_front_facing_validation_messages_are_human_readable(): void
    {
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale('en');

            $validator = Validator::make([
                'destination_url' => '',
                'foreground_color' => 'blue',
            ], [
                'destination_url' => ['required', 'url:http,https'],
                'foreground_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ]);

            $this->assertTrue($validator->fails());

            $messages = $validator->errors()->all();

            $this->assertSame('The destination URL is required.', $validator->errors()->first('destination_url'));

            foreach ($messages as $message) {
                $this->assertStringNotContainsString('validation.', $message);
                $this->assertStringNotContainsString('destination_url', $message);
                $this->assertStringNotContainsString('foreground_color', $message);
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_custom_front_facing_messages_are_translated(): void
    {
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale('en');

            $this->assertSame(
                'Enter a valid two-factor authentication code.',
                __('auth.two_factor')
            );

            $this->assertSame(
                'This short link path is reserved by the application.',
                __('openlink.validation.slug_reserved')
            );
        } finally {
            app()->setLocale($originalLocale);
        }
    }
}

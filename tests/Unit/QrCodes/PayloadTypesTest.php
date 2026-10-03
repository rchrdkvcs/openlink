<?php

namespace Tests\Unit\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\Payloads\PayloadTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PayloadTypesTest extends TestCase
{
    public static function encodings(): array
    {
        return [
            'url' => ['url', ['url' => ' https://example.com/a '], 'https://example.com/a', true],
            'text' => ['text', ['text' => ' Hello '], 'Hello', false],
            'email' => ['email', ['email' => 'a@b.co', 'subject' => 'Hi there', 'body' => ''], 'mailto:a@b.co?subject=Hi%20there', true],
            'phone' => ['phone', ['phone' => '+1 (555) 123'], 'tel:+1555123', true],
            'sms' => ['sms', ['phone' => '555', 'message' => 'See you'], 'sms:555?body=See%20you', true],
            'wifi' => ['wifi', ['ssid' => 'Lobby;1', 'encryption' => 'WPA', 'password' => 'p:w', 'hidden' => true], 'WIFI:T:WPA;S:Lobby\;1;P:p\:w;H:true;;', false],
            'wifi without password' => ['wifi', ['ssid' => 'Open', 'encryption' => 'nopass', 'password' => 'ignored'], 'WIFI:T:nopass;S:Open;P:;H:false;;', false],
            'vcard' => ['vcard', ['full_name' => 'Doe, Jane', 'email' => 'j@d.co'], "BEGIN:VCARD\nVERSION:3.0\nFN:Doe\\, Jane\nEMAIL:j@d.co\nEND:VCARD", false],
            'event' => ['event', ['title' => 'Launch', 'starts_at' => '2026-01-01T10:00:00Z', 'location' => 'HQ'], "BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//Openlink//QR Code//EN\nBEGIN:VEVENT\nSUMMARY:Launch\nDTSTART:20260101T100000Z\nDTEND:20260101T110000Z\nLOCATION:HQ\nEND:VEVENT\nEND:VCALENDAR", false],
            'location' => ['location', ['latitude' => '48.85', 'longitude' => '2.29', 'label' => 'Eiffel Tower'], 'geo:48.85,2.29?q=48.85,2.29(Eiffel%20Tower)', true],
            'location without label' => ['location', ['latitude' => '1', 'longitude' => '2'], 'geo:1,2', true],
            'raw' => ['raw', ['content' => ' BEGIN:VCARD '], 'BEGIN:VCARD', false],
        ];
    }

    #[DataProvider('encodings')]
    public function test_payload_types_encode_their_payload(string $type, array $payload, string $expected, bool $redirects): void
    {
        $payloadType = PayloadTypes::get($type);
        $content = $payloadType->encode($payload);

        $this->assertSame($expected, $content);
        $this->assertSame($redirects, $payloadType->redirects($content));
    }

    public function test_raw_payloads_redirect_only_for_navigable_schemes(): void
    {
        $raw = PayloadTypes::get('raw');

        $this->assertTrue($raw->redirects('https://example.com'));
        $this->assertTrue($raw->redirects('mailto:a@b.co'));
        $this->assertFalse($raw->redirects('WIFI:T:WPA;S:x;;'));
        $this->assertFalse($raw->redirects('BEGIN:VCARD'));
        $this->assertFalse($raw->redirects('plain text'));
    }

    public function test_registry_matches_the_supported_payload_types(): void
    {
        $this->assertSame(QrCode::PAYLOAD_TYPES, PayloadTypes::keys());

        foreach (PayloadTypes::all() as $type) {
            $descriptor = $type->descriptor();
            $this->assertSame(['label', 'hint', 'defaults', 'fields'], array_keys($descriptor));
            $this->assertNotEmpty($type->rules());
        }
    }
}

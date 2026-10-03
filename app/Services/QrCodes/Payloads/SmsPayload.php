<?php

namespace App\Services\QrCodes\Payloads;

class SmsPayload extends PayloadType
{
    public function key(): string
    {
        return 'sms';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'SMS',
            'hint' => 'Prefill a text message',
            'defaults' => ['phone' => '', 'message' => ''],
            'fields' => [
                ['key' => 'phone', 'label' => 'Phone number', 'control' => 'tel', 'placeholder' => '+15551234567'],
                ['key' => 'message', 'label' => 'Message', 'control' => 'textarea', 'rows' => 4, 'placeholder' => 'Optional SMS body'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'payload.phone' => ['required', 'string', 'max:80'],
            'payload.message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function encode(array $payload): string
    {
        $message = trim((string) ($payload['message'] ?? ''));

        return 'sms:'.PayloadText::phone((string) $payload['phone'])
            .($message === '' ? '' : '?body='.rawurlencode($message));
    }

    public function redirects(string $content): bool
    {
        return true;
    }
}

<?php

namespace App\Services\QrCodes\Payloads;

class PhonePayload extends PayloadType
{
    public function key(): string
    {
        return 'phone';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'Phone',
            'hint' => 'Start a phone call',
            'defaults' => ['phone' => ''],
            'fields' => [
                ['key' => 'phone', 'label' => 'Phone number', 'control' => 'tel', 'placeholder' => '+15551234567'],
            ],
        ];
    }

    public function rules(): array
    {
        return ['payload.phone' => ['required', 'string', 'max:80']];
    }

    public function encode(array $payload): string
    {
        return 'tel:'.PayloadText::phone((string) $payload['phone']);
    }

    public function redirects(string $content): bool
    {
        return true;
    }
}

<?php

namespace App\Services\QrCodes\Payloads;

class RawPayload extends PayloadType
{
    public function key(): string
    {
        return 'raw';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'Raw payload',
            'hint' => 'Any custom QR payload',
            'defaults' => ['content' => ''],
            'fields' => [
                ['key' => 'content', 'label' => 'Raw QR payload', 'control' => 'textarea', 'rows' => 8, 'placeholder' => 'BEGIN:VCARD...', 'class' => 'font-mono text-[13px]'],
            ],
        ];
    }

    public function rules(): array
    {
        return ['payload.content' => ['required', 'string', 'max:8000']];
    }

    public function encode(array $payload): string
    {
        return trim((string) $payload['content']);
    }

    public function redirects(string $content): bool
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $content) === 1
            && ! str_starts_with(strtoupper($content), 'WIFI:')
            && ! str_starts_with(strtoupper($content), 'BEGIN:');
    }
}

<?php

namespace App\Services\QrCodes\Payloads;

class UrlPayload extends PayloadType
{
    public function key(): string
    {
        return 'url';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'URL',
            'hint' => 'Open a web page',
            'defaults' => ['url' => ''],
            'fields' => [
                ['key' => 'url', 'label' => 'URL', 'control' => 'url', 'placeholder' => 'https://example.com'],
            ],
        ];
    }

    public function rules(): array
    {
        return ['payload.url' => ['required', 'url', 'max:2048']];
    }

    public function encode(array $payload): string
    {
        return trim((string) $payload['url']);
    }

    public function redirects(string $content): bool
    {
        return true;
    }
}

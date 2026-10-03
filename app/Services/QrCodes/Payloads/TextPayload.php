<?php

namespace App\Services\QrCodes\Payloads;

class TextPayload extends PayloadType
{
    public function key(): string
    {
        return 'text';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'Text',
            'hint' => 'Show plain text',
            'defaults' => ['text' => ''],
            'fields' => [
                ['key' => 'text', 'label' => 'Text', 'control' => 'textarea', 'rows' => 6, 'placeholder' => 'Plain text shown after scan'],
            ],
        ];
    }

    public function rules(): array
    {
        return ['payload.text' => ['required', 'string', 'max:8000']];
    }

    public function encode(array $payload): string
    {
        return trim((string) $payload['text']);
    }
}

<?php

namespace App\Services\QrCodes\Payloads;

class VcardPayload extends PayloadType
{
    public function key(): string
    {
        return 'vcard';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'vCard',
            'hint' => 'Share a contact card',
            'defaults' => ['full_name' => '', 'organization' => '', 'title' => '', 'phone' => '', 'email' => '', 'url' => '', 'address' => ''],
            'fields' => [
                ['key' => 'full_name', 'label' => 'Full name', 'control' => 'text', 'placeholder' => 'Jane Doe'],
                ['key' => 'organization', 'label' => 'Organization', 'control' => 'text'],
                ['key' => 'title', 'label' => 'Title', 'control' => 'text'],
                ['key' => 'phone', 'label' => 'Phone', 'control' => 'tel'],
                ['key' => 'email', 'label' => 'Email', 'control' => 'email'],
                ['key' => 'url', 'label' => 'Website', 'control' => 'url', 'placeholder' => 'https://example.com'],
                ['key' => 'address', 'label' => 'Address', 'control' => 'textarea', 'rows' => 3],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'payload.full_name' => ['required', 'string', 'max:255'],
            'payload.organization' => ['nullable', 'string', 'max:255'],
            'payload.title' => ['nullable', 'string', 'max:255'],
            'payload.phone' => ['nullable', 'string', 'max:80'],
            'payload.email' => ['nullable', 'email:rfc', 'max:255'],
            'payload.url' => ['nullable', 'url', 'max:2048'],
            'payload.address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function encode(array $payload): string
    {
        return implode("\n", [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'FN:'.PayloadText::vcard((string) $payload['full_name']),
            ...PayloadText::optionalLines($payload, [
                'organization' => 'ORG',
                'title' => 'TITLE',
                'phone' => 'TEL',
                'email' => 'EMAIL',
                'url' => 'URL',
                'address' => 'ADR',
            ]),
            'END:VCARD',
        ]);
    }
}

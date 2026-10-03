<?php

namespace App\Services\QrCodes\Payloads;

class EmailPayload extends PayloadType
{
    public function key(): string
    {
        return 'email';
    }

    public function descriptor(): array
    {
        return [
            'label' => 'Email',
            'hint' => 'Compose an email',
            'defaults' => ['email' => '', 'subject' => '', 'body' => ''],
            'fields' => [
                ['key' => 'email', 'label' => 'Email address', 'control' => 'email', 'placeholder' => 'hello@example.com'],
                ['key' => 'subject', 'label' => 'Subject', 'control' => 'text', 'placeholder' => 'Optional subject'],
                ['key' => 'body', 'label' => 'Body', 'control' => 'textarea', 'rows' => 4, 'placeholder' => 'Optional message body'],
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'payload.email' => ['required', 'email:rfc', 'max:255'],
            'payload.subject' => ['nullable', 'string', 'max:255'],
            'payload.body' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function encode(array $payload): string
    {
        $query = collect([
            'subject' => $payload['subject'] ?? null,
            'body' => $payload['body'] ?? null,
        ])->filter(fn ($value) => filled($value))->all();

        return 'mailto:'.trim((string) $payload['email'])
            .($query === [] ? '' : '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    public function redirects(string $content): bool
    {
        return true;
    }
}

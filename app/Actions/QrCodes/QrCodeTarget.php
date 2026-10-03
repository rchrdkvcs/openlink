<?php

namespace App\Actions\QrCodes;

use App\Models\ShortLink;

final class QrCodeTarget
{
    private function __construct(
        public readonly ?ShortLink $shortLink,
        public readonly ?string $payloadType,
        public readonly ?array $payload,
        public readonly ?string $content,
    ) {}

    public static function shortLink(ShortLink $shortLink): self
    {
        return new self($shortLink, null, null, null);
    }

    public static function direct(string $payloadType, array $payload, string $content): self
    {
        return new self(null, $payloadType, $payload, $content);
    }

    public function attributes(): array
    {
        return [
            'short_link_id' => $this->shortLink?->id,
            'payload_type' => $this->payloadType,
            'payload' => $this->payload,
            'content' => $this->content,
        ];
    }
}

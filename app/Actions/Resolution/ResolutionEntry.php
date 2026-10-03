<?php

namespace App\Actions\Resolution;

use App\Models\QrCode;
use App\Models\ShortLink;

final class ResolutionEntry
{
    private function __construct(
        public readonly ?string $slug = null,
        public readonly ?QrCode $qrCode = null,
        public readonly ?ShortLink $shortLink = null,
        public readonly ?string $password = null,
        public readonly ?int $qrCodeId = null,
    ) {}

    public static function shortUrl(string $slug): self
    {
        return new self(slug: trim($slug, '/'));
    }

    public static function qrCode(QrCode $qrCode): self
    {
        return new self(qrCode: $qrCode);
    }

    public static function passwordSubmission(ShortLink $shortLink, string $password, ?int $qrCodeId = null): self
    {
        return new self(shortLink: $shortLink, password: $password, qrCodeId: $qrCodeId);
    }

    public function isPasswordSubmission(): bool
    {
        return $this->password !== null;
    }
}

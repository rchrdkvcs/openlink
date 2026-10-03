<?php

namespace App\Actions\Resolution;

use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\Analytics\Outcome;

final class ResolutionResult
{
    public function __construct(
        public readonly ResolutionView $view,
        public readonly string $outcome,
        public readonly ?ShortLink $shortLink = null,
        public readonly ?QrCode $qrCode = null,
        public readonly ?string $redirectUrl = null,
    ) {}

    public static function unavailable(string $outcome, ?ShortLink $shortLink = null, ?QrCode $qrCode = null): self
    {
        return new self(ResolutionView::Unavailable, $outcome, $shortLink, $qrCode);
    }

    public static function redirect(string $outcome, string $url, ?ShortLink $shortLink = null, ?QrCode $qrCode = null): self
    {
        return new self(ResolutionView::Redirect, $outcome, $shortLink, $qrCode, $url);
    }

    public static function passwordForm(string $outcome, ShortLink $shortLink, ?QrCode $qrCode): self
    {
        return new self(ResolutionView::PasswordForm, $outcome, $shortLink, $qrCode);
    }

    public static function qrPayload(QrCode $qrCode): self
    {
        return new self(ResolutionView::QrPayload, Outcome::SUCCESS, qrCode: $qrCode);
    }

    public static function blocked(string $outcome, ShortLink $shortLink, ?QrCode $qrCode): self
    {
        if ($outcome === Outcome::SCHEDULED && $shortLink->activates_at) {
            return new self(ResolutionView::Scheduled, $outcome, $shortLink, $qrCode);
        }

        if ($shortLink->fallback_url) {
            return self::redirect($outcome, $shortLink->fallback_url, $shortLink, $qrCode);
        }

        return self::unavailable($outcome, $shortLink, $qrCode);
    }

    public function passwordRejected(): bool
    {
        return $this->outcome === Outcome::PASSWORD_FAILED;
    }
}

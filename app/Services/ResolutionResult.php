<?php

namespace App\Services;

use App\Models\QrCode;
use App\Models\ShortLink;

class ResolutionResult
{
    public function __construct(
        public readonly string $outcome,
        public readonly ?ShortLink $shortLink = null,
        public readonly ?QrCode $qrCode = null,
        public readonly ?string $redirectUrl = null,
        public readonly bool $requiresPassword = false,
    ) {
    }
}

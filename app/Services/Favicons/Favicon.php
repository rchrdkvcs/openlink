<?php

namespace App\Services\Favicons;

final class Favicon
{
    public function __construct(
        public readonly string $body,
        public readonly string $contentType,
    ) {}
}

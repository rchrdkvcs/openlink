<?php

namespace App\Actions\Resolution;

final class LinkPreview
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?string $url = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $redirectUrl = null,
    ) {}

    public static function redirect(string $url): self
    {
        return new self((string) config('app.name', 'Openlink'), redirectUrl: $url);
    }

    public static function neutral(): self
    {
        return new self((string) config('app.name', 'Openlink'));
    }
}

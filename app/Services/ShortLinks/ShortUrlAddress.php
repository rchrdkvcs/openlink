<?php

namespace App\Services\ShortLinks;

use App\Models\Domain;
use App\Models\ShortLink;
use Illuminate\Validation\ValidationException;

final class ShortUrlAddress
{
    public function __construct(
        public readonly Domain $domain,
        public readonly string $slug,
    ) {}

    public static function of(ShortLink $shortLink): self
    {
        $shortLink->loadMissing('domain');

        return new self($shortLink->domain, $shortLink->slug);
    }

    public function isTargetedBy(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $host === $this->domain->hostname && $path === trim($this->slug, '/');
    }

    public function rejectLoop(string $url, string $field): void
    {
        if ($this->isTargetedBy($url)) {
            throw ValidationException::withMessages([$field => 'Destination URL cannot point to the same short URL.']);
        }
    }
}

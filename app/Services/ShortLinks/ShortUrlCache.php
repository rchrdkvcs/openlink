<?php

namespace App\Services\ShortLinks;

use App\Models\Domain;
use App\Models\ShortLink;
use Illuminate\Support\Facades\Cache;

class ShortUrlCache
{
    public function forgetForShortLink(ShortLink $shortLink): void
    {
        $keys = [];
        $domain = $shortLink->domain()->first();

        if ($domain) {
            $keys[] = $this->key($domain, $shortLink->slug);
        }

        $originalSlug = $shortLink->getOriginal('slug');
        $originalDomainId = $shortLink->getOriginal('domain_id');

        if ($originalSlug !== null && ($originalSlug !== $shortLink->slug || $originalDomainId !== $shortLink->domain_id)) {
            $originalDomain = $originalDomainId === $shortLink->domain_id
                ? $domain
                : Domain::query()->find($originalDomainId);

            if ($originalDomain) {
                $keys[] = $this->key($originalDomain, $originalSlug);
            }
        }

        foreach (array_unique($keys) as $key) {
            Cache::forget($key);
        }
    }

    public function forgetForDomain(Domain $domain): void
    {
        $domain->shortLinks()->pluck('slug')->each(
            fn (string $slug) => Cache::forget($this->key($domain, $slug))
        );
    }

    public function key(Domain $domain, string $slug): string
    {
        return "resolution:{$domain->hostname}:{$slug}";
    }
}

<?php

namespace App\Services\ShortLinks;

use App\Models\Domain;
use App\Models\ShortLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ShortUrlCache
{
    private const TTL_MINUTES = 10;

    public function shortLinkId(Domain $domain, string $slug): ?int
    {
        return Cache::remember(
            $this->key($domain, $slug),
            now()->addMinutes(self::TTL_MINUTES),
            fn (): ?int => ShortLink::query()
                ->where('domain_id', $domain->id)
                ->where('slug', $slug)
                ->value('id'),
        );
    }

    public function forgetForShortLink(ShortLink $shortLink): void
    {
        $previous = $shortLink->getPrevious();
        $addresses = [[$shortLink->domain_id, $shortLink->slug]];

        if (array_key_exists('slug', $previous) || array_key_exists('domain_id', $previous)) {
            $addresses[] = [
                $previous['domain_id'] ?? $shortLink->domain_id,
                $previous['slug'] ?? $shortLink->slug,
            ];
        }

        $hostnames = $this->hostnames($shortLink, array_unique(array_column($addresses, 0)));

        foreach ($addresses as [$domainId, $slug]) {
            if ($slug !== null && $hostnames->has($domainId)) {
                Cache::forget($this->keyFor($hostnames[$domainId], $slug));
            }
        }
    }

    private function hostnames(ShortLink $shortLink, array $domainIds): Collection
    {
        $domain = $shortLink->relationLoaded('domain') ? $shortLink->getRelation('domain') : null;

        if ($domain instanceof Domain && $domainIds === [$domain->getKey()]) {
            return collect([$domain->getKey() => $domain->hostname]);
        }

        return Domain::query()->whereKey($domainIds)->pluck('hostname', 'id');
    }

    public function forgetForDomain(Domain $domain, ?string $previousHostname = null): void
    {
        $hostnames = array_unique(array_filter([$domain->hostname, $previousHostname]));

        $domain->shortLinks()->pluck('slug')->each(function (string $slug) use ($hostnames): void {
            foreach ($hostnames as $hostname) {
                Cache::forget($this->keyFor($hostname, $slug));
            }
        });
    }

    public function key(Domain $domain, string $slug): string
    {
        return $this->keyFor($domain->hostname, $slug);
    }

    private function keyFor(string $hostname, string $slug): string
    {
        return "resolution:{$hostname}:{$slug}";
    }
}

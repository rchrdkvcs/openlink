<?php

namespace App\Services\ShortLinks\History;

use App\Models\ShortLink;

class UrlRedaction
{
    public const MASK = '•••';

    public function __construct(private readonly string $key) {}

    public function mask(?string $url): ?string
    {
        if ($url === null || strpbrk($url, '?#') === false) {
            return $url;
        }

        [$withoutFragment, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        [$base, $query] = array_pad(explode('?', $withoutFragment, 2), 2, null);

        $masked = $base;

        if ($query !== null) {
            $masked .= '?'.collect(explode('&', $query))
                ->map(fn (string $pair) => str_contains($pair, '=') ? strstr($pair, '=', true).'='.self::MASK : self::MASK)
                ->implode('&');
        }

        return $fragment === null ? $masked : $masked.'#'.self::MASK;
    }

    public function digest(?string $url): ?string
    {
        return $url === null || $this->mask($url) === $url ? null : hash_hmac('sha256', $url, $this->key);
    }

    public function currentUrls(ShortLink $shortLink): array
    {
        $shortLink->loadMissing('routingRules.variants');

        return collect([$shortLink->destination_url, $shortLink->fallback_url])
            ->merge($shortLink->routingRules->pluck('destination_url'))
            ->merge($shortLink->routingRules->flatMap(fn ($rule) => $rule->variants->pluck('destination_url')))
            ->filter()
            ->mapWithKeys(fn (string $url) => [$this->digest($url) ?? $url => $url])
            ->all();
    }
}

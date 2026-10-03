<?php

namespace App\Services\Favicons;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;

class Favicons
{
    public const FOUND_TTL = 604800;

    public const MISSING_TTL = 3600;

    private const STANDARD_PATHS = [
        '/favicon.png',
        '/favicon.svg',
        '/apple-touch-icon.png',
        '/apple-touch-icon-precomposed.png',
    ];

    public function __construct(
        private readonly Repository $cache,
        private readonly PublicUrl $urls,
        private readonly FaviconFetcher $fetcher,
        private readonly IconCandidates $candidates,
    ) {}

    public function forUrl(string $url): ?Favicon
    {
        $origin = $this->urls->origin($url);

        if ($origin === null) {
            return null;
        }

        $key = 'favicons:v1:'.hash('sha256', $origin);
        $cached = $this->cached($key) ?? $this->resolveOnce($key, $origin, $url);

        return $cached['found'] ? new Favicon($cached['body'], $cached['content_type']) : null;
    }

    private function resolveOnce(string $key, string $origin, string $url): array
    {
        try {
            return $this->cache->lock($key.':lock', 30)->block(5, fn (): array => $this->cached($key) ?? $this->resolveAndStore($key, $origin, $url));
        } catch (LockTimeoutException) {
            return $this->cached($key) ?? $this->resolveAndStore($key, $origin, $url);
        }
    }

    private function resolveAndStore(string $key, string $origin, string $url): array
    {
        $favicon = $this->resolve($origin, $url);

        if ($favicon === null) {
            $this->cache->put($key, ['found' => false], self::MISSING_TTL);

            return ['found' => false];
        }

        $entry = ['found' => true, 'body' => $favicon->body, 'content_type' => $favicon->contentType];
        $this->cache->put($key, $entry, self::FOUND_TTL);

        return $entry;
    }

    private function resolve(string $origin, string $url): ?Favicon
    {
        $favicon = $this->fetcher->image($origin.'/favicon.ico');

        if ($favicon !== null) {
            return $favicon;
        }

        $page = $this->fetcher->html($url);
        $candidates = [
            ...($page ? $this->candidates->fromPage($page['url'], $page['body']) : []),
            ...array_map(fn (string $path) => $origin.$path, self::STANDARD_PATHS),
        ];

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if ($this->urls->isAllowed($candidate) && ($favicon = $this->fetcher->image($candidate))) {
                return $favicon;
            }
        }

        return null;
    }

    private function cached(string $key): ?array
    {
        $cached = $this->cache->get($key);

        return is_array($cached) && isset($cached['found']) ? $cached : null;
    }
}

<?php

namespace App\Services\Favicons;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FaviconFetcher
{
    private const MAX_HOPS = 3;

    private const IMAGE_TYPES = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/svg+xml',
        'image/vnd.microsoft.icon',
        'image/webp',
        'image/x-icon',
        'application/octet-stream',
    ];

    private const JSON_TYPES = ['application/json', 'application/manifest+json', 'text/json'];

    public function __construct(private readonly PublicUrl $urls) {}

    public function image(string $url): ?Favicon
    {
        $response = $this->get($url)['response'] ?? null;

        if (! $response || ! $response->ok() || ! in_array($this->mediaType($response), self::IMAGE_TYPES, true)) {
            return null;
        }

        $type = $this->mediaType($response);

        return new Favicon($response->body(), $type === 'application/octet-stream' ? 'image/x-icon' : $type);
    }

    public function html(string $url): ?array
    {
        $fetched = $this->get($url);
        $response = $fetched['response'] ?? null;

        return $response && $response->ok() && $this->mediaType($response) === 'text/html'
            ? ['url' => $fetched['url'], 'body' => $response->body()]
            : null;
    }

    public function json(string $url): ?array
    {
        $response = $this->get($url)['response'] ?? null;

        if (! $response || ! $response->ok() || ! in_array($this->mediaType($response), self::JSON_TYPES, true)) {
            return null;
        }

        $decoded = json_decode($response->body(), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function get(string $url): ?array
    {
        $current = $url;

        for ($hop = 0; $hop < self::MAX_HOPS; $hop++) {
            try {
                $response = Http::timeout(3)
                    ->connectTimeout(2)
                    ->withoutRedirecting()
                    ->withHeaders(['User-Agent' => 'Openlink favicon fetcher'])
                    ->get($current);
            } catch (ConnectionException) {
                return null;
            }

            if (! $response->redirect()) {
                return ['response' => $response, 'url' => $current];
            }

            $current = $this->urls->redirectTarget($current, $response->header('Location'));

            if (! $current) {
                return null;
            }
        }

        return null;
    }

    private function mediaType(Response $response): string
    {
        return strtolower(strtok($response->header('Content-Type', ''), ';') ?: '');
    }
}

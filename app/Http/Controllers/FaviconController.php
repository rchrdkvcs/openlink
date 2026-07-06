<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class FaviconController extends Controller
{
    private const STANDARD_PATHS = [
        '/favicon.ico',
        '/favicon.png',
        '/favicon.svg',
        '/apple-touch-icon.png',
        '/apple-touch-icon-precomposed.png',
    ];

    private const ALLOWED_TYPES = [
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/svg+xml',
        'image/vnd.microsoft.icon',
        'image/webp',
        'image/x-icon',
        'application/octet-stream',
    ];

    public function show(Request $request): Response
    {
        $data = $request->validate([
            'url' => ['required', 'url'],
        ]);

        if (! $this->isAllowedUrl($data['url'])) {
            abort(404);
        }

        $response = $this->firstImageResponse($data['url']);

        if (! $response || ! $response->ok() || ! $this->isImage($response)) {
            abort(404);
        }

        return response($response->body(), 200, [
            'Cache-Control' => 'private, max-age=86400',
            'Content-Type' => $this->contentType($response),
        ]);
    }

    private function firstImageResponse(string $url): ?ClientResponse
    {
        $origin = $this->originUrl($url);

        if ($origin) {
            $response = $this->fetch($origin.'/favicon.ico');

            if ($response && $response->ok() && $this->isImage($response)) {
                return $response;
            }
        }

        $result = $this->fetchWithUrl($url);
        $response = $result['response'] ?? null;
        $candidates = [];

        if ($response && $response->ok() && $this->isHtml($response)) {
            $candidates = [
                ...$candidates,
                ...$this->candidateUrlsFromHtml($result['url'], $response->body()),
            ];
        }

        if ($origin) {
            $candidates = [
                ...$candidates,
                ...array_map(fn (string $path) => $origin.$path, array_slice(self::STANDARD_PATHS, 1)),
            ];
        }

        foreach (array_values(array_unique($candidates)) as $candidate) {
            if (! $this->isAllowedUrl($candidate)) {
                continue;
            }

            $iconResponse = $this->fetch($candidate);

            if ($iconResponse && $iconResponse->ok() && $this->isImage($iconResponse)) {
                return $iconResponse;
            }
        }

        return null;
    }

    private function fetch(string $url): ?ClientResponse
    {
        return $this->fetchWithUrl($url)['response'] ?? null;
    }

    /** @return array{response: ClientResponse, url: string}|null */
    private function fetchWithUrl(string $url): ?array
    {
        $current = $url;

        for ($i = 0; $i < 3; $i++) {
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

            $next = $this->redirectUrl($current, $response->header('Location'));

            if (! $next) {
                return null;
            }

            $current = $next;
        }

        return null;
    }

    /** @return list<string> */
    private function candidateUrlsFromHtml(string $pageUrl, string $html): array
    {
        libxml_use_internal_errors(true);

        $document = new \DOMDocument;
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();

        if (! $loaded) {
            return [];
        }

        $candidates = [];
        $manifestUrls = [];

        foreach ($document->getElementsByTagName('link') as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            $href = trim($link->getAttribute('href'));

            if ($href === '') {
                continue;
            }

            $resolved = $this->absoluteUrl($pageUrl, $href);

            if (! $resolved || ! $this->isAllowedUrl($resolved)) {
                continue;
            }

            if (str_contains($rel, 'icon')) {
                $candidates[] = [
                    'url' => $resolved,
                    'score' => $this->iconScore($rel, $link->getAttribute('sizes'), $link->getAttribute('type')),
                ];
            } elseif (str_contains($rel, 'manifest')) {
                $manifestUrls[] = $resolved;
            }
        }

        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return [
            ...array_column($candidates, 'url'),
            ...$this->candidateUrlsFromManifests($manifestUrls),
        ];
    }

    /** @param list<string> $manifestUrls @return list<string> */
    private function candidateUrlsFromManifests(array $manifestUrls): array
    {
        $candidates = [];

        foreach ($manifestUrls as $manifestUrl) {
            $response = $this->fetch($manifestUrl);

            if (! $response || ! $response->ok() || ! $this->isJson($response)) {
                continue;
            }

            $manifest = json_decode($response->body(), true);

            if (! is_array($manifest) || ! isset($manifest['icons']) || ! is_array($manifest['icons'])) {
                continue;
            }

            foreach ($manifest['icons'] as $icon) {
                if (! is_array($icon) || ! isset($icon['src']) || ! is_string($icon['src'])) {
                    continue;
                }

                $resolved = $this->absoluteUrl($manifestUrl, $icon['src']);

                if ($resolved && $this->isAllowedUrl($resolved)) {
                    $candidates[] = [
                        'url' => $resolved,
                        'score' => $this->iconScore('manifest icon', (string) ($icon['sizes'] ?? ''), (string) ($icon['type'] ?? '')),
                    ];
                }
            }
        }

        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_column($candidates, 'url');
    }

    private function iconScore(string $rel, string $sizes, string $type): int
    {
        $score = 0;

        if (str_contains($rel, 'apple-touch-icon')) {
            $score += 30;
        } elseif (str_contains($rel, 'icon')) {
            $score += 20;
        }

        if (str_contains($type, 'svg')) {
            $score += 60;
        } elseif (str_contains($type, 'png') || str_contains($type, 'webp')) {
            $score += 40;
        } elseif (str_contains($type, 'icon')) {
            $score += 20;
        }

        if (preg_match_all('/(\d+)x(\d+)/', $sizes, $matches, PREG_SET_ORDER)) {
            $score += max(array_map(fn (array $match) => (int) $match[1], $matches));
        }

        return $score;
    }

    private function redirectUrl(string $current, ?string $location): ?string
    {
        if (! $location) {
            return null;
        }

        $base = parse_url($current);
        $scheme = $base['scheme'] ?? null;
        $host = $base['host'] ?? null;

        if (str_starts_with($location, '//') && $scheme) {
            $location = $scheme.':'.$location;
        } elseif (str_starts_with($location, '/') && $scheme && $host) {
            $location = $scheme.'://'.$host.$location;
        }

        return $this->isAllowedUrl($location) ? $location : null;
    }

    private function absoluteUrl(string $baseUrl, string $url): ?string
    {
        if (str_starts_with($url, 'data:')) {
            return null;
        }

        $base = parse_url($baseUrl);
        $scheme = $base['scheme'] ?? null;
        $host = $base['host'] ?? null;

        if (str_starts_with($url, '//') && $scheme) {
            return $scheme.':'.$url;
        }

        if (str_starts_with($url, '/') && $scheme && $host) {
            return $scheme.'://'.$host.$url;
        }

        if (! parse_url($url, PHP_URL_SCHEME) && $scheme && $host) {
            $path = $base['path'] ?? '/';
            $directory = rtrim(str_replace('\\', '/', dirname($path)), '/');

            return $scheme.'://'.$host.($directory ? '/'.$directory : '').'/'.$url;
        }

        return $url;
    }

    private function originUrl(string $url): ?string
    {
        if (! $this->isAllowedUrl($url)) {
            return null;
        }

        $parts = parse_url($url);

        return strtolower((string) $parts['scheme']).'://'.$parts['host'];
    }

    private function isAllowedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $parts['host'] ?? null;

        return in_array($scheme, ['http', 'https'], true) && $host && $this->isPublicHost($host);
    }

    private function isPublicHost(string $host): bool
    {
        $host = trim(strtolower($host), '[]');

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        $addresses = gethostbynamel($host);

        if (! $addresses) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    private function isImage(ClientResponse $response): bool
    {
        $contentType = strtolower(strtok($response->header('Content-Type', ''), ';') ?: '');

        return in_array($contentType, self::ALLOWED_TYPES, true);
    }

    private function isHtml(ClientResponse $response): bool
    {
        $contentType = strtolower(strtok($response->header('Content-Type', ''), ';') ?: '');

        return $contentType === 'text/html';
    }

    private function isJson(ClientResponse $response): bool
    {
        $contentType = strtolower(strtok($response->header('Content-Type', ''), ';') ?: '');

        return in_array($contentType, ['application/json', 'application/manifest+json', 'text/json'], true);
    }

    private function contentType(ClientResponse $response): string
    {
        $contentType = strtolower(strtok($response->header('Content-Type', ''), ';') ?: '');

        return $contentType === 'application/octet-stream' ? 'image/x-icon' : $contentType;
    }
}

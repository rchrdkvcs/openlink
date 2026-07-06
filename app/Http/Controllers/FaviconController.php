<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class FaviconController extends Controller
{
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

        $faviconUrl = $this->faviconUrl($data['url']);

        if (! $faviconUrl) {
            abort(404);
        }

        $response = $this->fetch($faviconUrl);

        if (! $response || ! $response->ok() || ! $this->isImage($response)) {
            abort(404);
        }

        return response($response->body(), 200, [
            'Cache-Control' => 'private, max-age=86400',
            'Content-Type' => $this->contentType($response),
        ]);
    }

    private function faviconUrl(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host || ! $this->isPublicHost($host)) {
            return null;
        }

        return $scheme.'://'.$host.'/favicon.ico';
    }

    private function fetch(string $url): ?ClientResponse
    {
        $current = $url;

        for ($i = 0; $i < 3; $i++) {
            $response = Http::timeout(3)
                ->connectTimeout(2)
                ->withoutRedirecting()
                ->withHeaders(['User-Agent' => 'Openlink favicon fetcher'])
                ->get($current);

            if (! $response->redirect()) {
                return $response;
            }

            $next = $this->redirectUrl($current, $response->header('Location'));

            if (! $next) {
                return null;
            }

            $current = $next;
        }

        return null;
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

        $parts = parse_url($location);
        $targetScheme = strtolower((string) ($parts['scheme'] ?? ''));
        $targetHost = $parts['host'] ?? null;

        if (! in_array($targetScheme, ['http', 'https'], true) || ! $targetHost || ! $this->isPublicHost($targetHost)) {
            return null;
        }

        return $location;
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

    private function contentType(ClientResponse $response): string
    {
        $contentType = strtolower(strtok($response->header('Content-Type', ''), ';') ?: '');

        return $contentType === 'application/octet-stream' ? 'image/x-icon' : $contentType;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class UpdateStatus
{
    /** @return array<string, mixed> */
    public function get(): array
    {
        $current = (string) config('openlink.version');
        $latest = Cache::remember('openlink.latest-release', now()->addMinutes(15), function (): ?array {
            try {
                $response = Http::acceptJson()
                    ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                    ->timeout(5)
                    ->get('https://api.github.com/repos/rchrdkvcs/openlink/releases/latest');

                if (! $response->successful()) {
                    return null;
                }

                $tag = $response->json('tag_name');

                if (! is_string($tag) || ! preg_match('/^v\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $tag)) {
                    return null;
                }

                return ['version' => $tag, 'url' => $response->json('html_url')];
            } catch (\Throwable) {
                return null;
            }
        });

        $available = $latest && preg_match('/^v\d+\.\d+\.\d+/', $current)
            && version_compare(ltrim($latest['version'], 'v'), ltrim($current, 'v'), '>');

        $state = null;
        $requestPath = storage_path('app/update-request');
        $statusPath = storage_path('app/update-status');

        if (is_file($requestPath)) {
            $state = 'pending';
        } elseif (is_file($statusPath)) {
            $state = trim((string) file_get_contents($statusPath));
        }

        $heartbeatPath = storage_path('app/update-heartbeat');
        $updaterRunning = is_file($heartbeatPath) && filemtime($heartbeatPath) > time() - 20;

        return [
            'current' => $current,
            'latest' => $latest,
            'available' => (bool) $available,
            'canUpdate' => (bool) config('openlink.updater_enabled') && config('openlink.image_tag') === 'latest' && $updaterRunning,
            'state' => in_array($state, ['pending', 'running', 'succeeded', 'failed'], true) ? $state : null,
        ];
    }
}

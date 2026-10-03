<?php

namespace App\Services\Favicons;

use DOMDocument;

class IconCandidates
{
    public function __construct(
        private readonly PublicUrl $urls,
        private readonly FaviconFetcher $fetcher,
    ) {}

    public function fromPage(string $pageUrl, string $html): array
    {
        $document = $this->parse($html);

        if ($document === null) {
            return [];
        }

        $icons = [];
        $manifests = [];

        foreach ($document->getElementsByTagName('link') as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            $href = trim($link->getAttribute('href'));
            $resolved = $href === '' ? null : $this->urls->absolute($pageUrl, $href);

            if (! $resolved || ! $this->urls->isAllowed($resolved)) {
                continue;
            }

            if (str_contains($rel, 'icon')) {
                $icons[] = ['url' => $resolved, 'score' => $this->score($rel, $link->getAttribute('sizes'), $link->getAttribute('type'))];
            } elseif (str_contains($rel, 'manifest')) {
                $manifests[] = $resolved;
            }
        }

        return [...$this->ranked($icons), ...$this->fromManifests($manifests)];
    }

    private function fromManifests(array $manifestUrls): array
    {
        $icons = [];

        foreach ($manifestUrls as $manifestUrl) {
            $manifest = $this->fetcher->json($manifestUrl);

            foreach (is_array($manifest['icons'] ?? null) ? $manifest['icons'] : [] as $icon) {
                if (! is_array($icon) || ! is_string($icon['src'] ?? null)) {
                    continue;
                }

                $resolved = $this->urls->absolute($manifestUrl, $icon['src']);

                if ($resolved && $this->urls->isAllowed($resolved)) {
                    $icons[] = ['url' => $resolved, 'score' => $this->score('manifest icon', (string) ($icon['sizes'] ?? ''), (string) ($icon['type'] ?? ''))];
                }
            }
        }

        return $this->ranked($icons);
    }

    private function parse(string $html): ?DOMDocument
    {
        libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML($html);
        libxml_clear_errors();

        return $loaded ? $document : null;
    }

    private function ranked(array $icons): array
    {
        usort($icons, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return array_column($icons, 'url');
    }

    private function score(string $rel, string $sizes, string $type): int
    {
        $score = match (true) {
            str_contains($rel, 'apple-touch-icon') => 30,
            str_contains($rel, 'icon') => 20,
            default => 0,
        };

        $score += match (true) {
            str_contains($type, 'svg') => 60,
            str_contains($type, 'png'), str_contains($type, 'webp') => 40,
            str_contains($type, 'icon') => 20,
            default => 0,
        };

        if (preg_match_all('/(\d+)x(\d+)/', $sizes, $matches, PREG_SET_ORDER)) {
            $score += max(array_map(fn (array $match) => (int) $match[1], $matches));
        }

        return $score;
    }
}

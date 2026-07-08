<?php

namespace App\Services\ShortLinks;

use App\Models\Domain;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\SlugService;

class ShortUrlComposition
{
    public function __construct(private readonly SlugService $slugs) {}

    public function domainForWorkspace(mixed $domainId, Workspace $workspace, ?Domain $fallbackDomain = null): ?Domain
    {
        $domainId = (int) ($domainId ?: $fallbackDomain?->id);

        if ($domainId <= 0) {
            return null;
        }

        return Domain::query()
            ->whereKey($domainId)
            ->where(fn ($query) => $query->where('workspace_id', $workspace->id)->orWhere('is_default', true))
            ->first();
    }

    public function requireUsableDomain(mixed $domainId, Workspace $workspace, ?Domain $fallbackDomain = null): Domain
    {
        $domain = $this->domainForWorkspace($domainId, $workspace, $fallbackDomain);

        abort_unless($domain, 422, 'Domain does not belong to this workspace.');
        abort_unless($domain->isUsable(), 422, 'Domain is not active or is disabled.');

        return $domain;
    }

    /** @param array<string, mixed> $data */
    public function slugForCreate(Domain $domain, array $data): string
    {
        return filled($data['slug'] ?? null)
            ? $this->slugs->validateCustom($domain, (string) $data['slug'])
            : $this->slugs->generate($domain);
    }

    /** @param array<string, mixed> $data */
    public function slugForUpdate(ShortLink $shortLink, Domain $domain, array $data): string
    {
        $submitted = trim((string) ($data['slug'] ?? $shortLink->slug), '/');

        if ($submitted !== $shortLink->slug || $domain->id !== $shortLink->domain_id) {
            return $this->slugs->validateCustom($domain, $submitted);
        }

        return $shortLink->slug;
    }

    public function assertNoLoop(Domain $domain, string $slug, string $destinationUrl): void
    {
        $targetHost = parse_url($destinationUrl, PHP_URL_HOST);
        $targetPath = trim((string) parse_url($destinationUrl, PHP_URL_PATH), '/');

        if ($targetHost === $domain->hostname && $targetPath === trim($slug, '/')) {
            abort(422, 'Destination URL cannot point to the same short URL.');
        }
    }
}

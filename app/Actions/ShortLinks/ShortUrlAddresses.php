<?php

namespace App\Actions\ShortLinks;

use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\ShortLinks\ShortUrlAddress;
use App\Services\SlugService;

class ShortUrlAddresses
{
    public function __construct(
        private readonly AvailableDomains $domains,
        private readonly SlugService $slugs,
    ) {}

    public function forNewLink(Workspace $workspace, ?int $domainId, ?string $slug): ShortUrlAddress
    {
        $domain = $this->domains->requireUsable($workspace, $domainId);

        return new ShortUrlAddress($domain, filled($slug)
            ? $this->slugs->validateCustom($domain, $slug)
            : $this->slugs->generate($domain));
    }

    public function forExistingLink(ShortLink $shortLink, ?int $domainId, ?string $slug): ShortUrlAddress
    {
        $current = ShortUrlAddress::of($shortLink);
        $domain = $domainId && $domainId !== $shortLink->domain_id
            ? $this->domains->requireUsable($shortLink->workspace, $domainId)
            : $current->domain;
        $slug = trim($slug ?? $current->slug, '/');

        if ($slug === $current->slug && $domain->is($current->domain)) {
            return $current;
        }

        return new ShortUrlAddress($domain, $this->slugs->validateCustom($domain, $slug));
    }
}

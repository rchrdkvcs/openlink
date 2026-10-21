<?php

namespace App\Actions\Resolution;

use App\Actions\Domains\DomainLifecycle;
use App\Models\Domain;
use App\Models\ShortLink;
use App\Services\ShortLinks\ShortLinkLifecycle;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Http\Request;

class LinkPreviewResolution
{
    public function __construct(
        private readonly DomainLifecycle $domains,
        private readonly ShortLinkLifecycle $lifecycle,
        private readonly ShortUrlCache $shortUrls,
    ) {}

    public function resolve(Request $request, string $slug): ?LinkPreview
    {
        $domain = Domain::query()->where('hostname', $request->getHost())->first();

        if ($domain) {
            $this->domains->activateOnObservedTraffic($request, $domain);
        }

        if (! $domain || ! $domain->isUsable()) {
            return LinkPreview::neutral();
        }

        $shortLinkId = $this->shortUrls->shortLinkId($domain, trim($slug, '/'));
        $shortLink = $shortLinkId ? ShortLink::query()->with('domain')->find($shortLinkId) : null;

        if (! $shortLink || ! $this->isPublic($shortLink)) {
            return LinkPreview::neutral();
        }

        if (! $shortLink->hasCustomPreview()) {
            return null;
        }

        return new LinkPreview(
            $shortLink->preview_title ?: $shortLink->domain->hostname.'/'.$shortLink->slug,
            $shortLink->preview_description,
            $shortLink->shortUrl(),
            $shortLink->publicPreviewImageUrl(),
        );
    }

    public function isPublic(ShortLink $shortLink): bool
    {
        return $shortLink->domain?->isUsable()
            && ! $shortLink->hasPassword()
            && $this->lifecycle->unavailableOutcome($shortLink) === null;
    }
}

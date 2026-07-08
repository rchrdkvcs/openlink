<?php

namespace App\Actions\Resolution;

use App\Actions\Analytics\RecordAnalytics;
use App\Actions\Domains\DomainLifecycle;
use App\Models\Domain;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\Analytics\Outcome;
use App\Services\ResolutionContextFactory;
use App\Services\ResolutionResult;
use App\Services\ShortLinks\ShortLinkLifecycle;
use App\Services\ShortLinks\SmartRouting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ResolvePublicLink
{
    public function __construct(
        private readonly RecordAnalytics $analytics,
        private readonly ResolutionContextFactory $contexts,
        private readonly DomainLifecycle $domains,
        private readonly ShortLinkLifecycle $lifecycle,
        private readonly SmartRouting $routing,
    ) {}

    public function resolve(Request $request, string $slug, ?QrCode $qrCode = null): ResolutionResult
    {
        $domain = $qrCode?->shortLink?->domain
            ?? Domain::query()->where('hostname', $request->getHost())->first();

        if ($domain) {
            $this->domains->activateOnObservedTraffic($request, $domain);
        }

        if (! $domain || ! $domain->isUsable()) {
            return new ResolutionResult(Outcome::DOMAIN_UNAVAILABLE);
        }

        $shortLink = $qrCode?->shortLink
            ?? $this->linkFor($domain, trim($slug, '/'));

        if (! $shortLink) {
            return new ResolutionResult(Outcome::NOT_FOUND);
        }

        return $this->resolveShortLink($request, $shortLink, $qrCode);
    }

    public function resolveShortLink(Request $request, ShortLink $shortLink, ?QrCode $qrCode = null): ResolutionResult
    {
        $shortLink->loadMissing('domain');

        if ($shortLink->domain) {
            $this->domains->activateOnObservedTraffic($request, $shortLink->domain);
        }

        if (! $shortLink->domain || ! $shortLink->domain->isUsable()) {
            return new ResolutionResult(
                outcome: Outcome::DOMAIN_UNAVAILABLE,
                shortLink: $shortLink,
                qrCode: $qrCode,
            );
        }

        $unavailableOutcome = $this->lifecycle->unavailableOutcome($shortLink);

        if ($unavailableOutcome) {
            $this->analytics->record($request, $shortLink, $qrCode, $qrCode ? RecordAnalytics::METRIC_SCAN : RecordAnalytics::METRIC_VISIT, $unavailableOutcome);

            return new ResolutionResult(
                outcome: $unavailableOutcome,
                shortLink: $shortLink,
                qrCode: $qrCode,
                redirectUrl: $shortLink->fallback_url,
            );
        }

        if ($shortLink->hasPassword() && ! $request->session()->get($this->passwordSessionKey($shortLink))) {
            return new ResolutionResult(
                outcome: 'password_required',
                shortLink: $shortLink,
                qrCode: $qrCode,
                requiresPassword: true,
            );
        }

        $context = $this->contexts->fromRequest($request);
        $decision = $this->routing->resolve($shortLink, $context);

        $shortLink->increment('successful_visits');
        $this->analytics->record(
            $request,
            $shortLink,
            $qrCode,
            $qrCode ? RecordAnalytics::METRIC_SCAN : RecordAnalytics::METRIC_VISIT,
            Outcome::SUCCESS,
            $context,
            $decision->rule,
            $decision->variant,
        );

        return new ResolutionResult(
            outcome: Outcome::SUCCESS,
            shortLink: $shortLink,
            qrCode: $qrCode,
            redirectUrl: $decision->destinationUrl,
        );
    }

    public function passwordSessionKey(ShortLink $shortLink): string
    {
        return $this->lifecycle->passwordSessionKey($shortLink);
    }

    private function linkFor(Domain $domain, string $slug): ?ShortLink
    {
        $shortLinkId = Cache::remember("resolution:{$domain->hostname}:{$slug}", now()->addMinutes(10), function () use ($domain, $slug): ?int {
            return ShortLink::query()
                ->where('domain_id', $domain->id)
                ->where('slug', $slug)
                ->value('id');
        });

        return $shortLinkId ? ShortLink::query()->with('domain')->find($shortLinkId) : null;
    }
}

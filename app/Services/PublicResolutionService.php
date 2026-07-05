<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\QrCode;
use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicResolutionService
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function resolve(Request $request, string $slug, ?QrCode $qrCode = null): ResolutionResult
    {
        $domain = $qrCode?->shortLink?->domain
            ?? Domain::query()->where('hostname', $request->getHost())->first();

        if (! $domain || ! $domain->isUsable()) {
            return new ResolutionResult(AnalyticsService::OUTCOME_DOMAIN_UNAVAILABLE);
        }

        $shortLink = $qrCode?->shortLink
            ?? $this->linkFor($domain, trim($slug, '/'));

        if (! $shortLink) {
            return new ResolutionResult(AnalyticsService::OUTCOME_NOT_FOUND);
        }

        return $this->resolveShortLink($request, $shortLink, $qrCode);
    }

    public function resolveShortLink(Request $request, ShortLink $shortLink, ?QrCode $qrCode = null): ResolutionResult
    {
        $shortLink->loadMissing('domain');

        if (! $shortLink->domain || ! $shortLink->domain->isUsable()) {
            return new ResolutionResult(
                outcome: AnalyticsService::OUTCOME_DOMAIN_UNAVAILABLE,
                shortLink: $shortLink,
                qrCode: $qrCode,
            );
        }

        $unavailableOutcome = $this->unavailableOutcome($shortLink);

        if ($unavailableOutcome) {
            $this->analytics->queue($request, $shortLink, $qrCode, $qrCode ? 'scan' : 'visit', $unavailableOutcome);

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

        $shortLink->increment('successful_visits');
        $this->analytics->queue($request, $shortLink, $qrCode, $qrCode ? 'scan' : 'visit', AnalyticsService::OUTCOME_SUCCESS);

        return new ResolutionResult(
            outcome: AnalyticsService::OUTCOME_SUCCESS,
            shortLink: $shortLink,
            qrCode: $qrCode,
            redirectUrl: $shortLink->destination_url,
        );
    }

    public function passwordSessionKey(ShortLink $shortLink): string
    {
        return 'openlink_protected_link_'.$shortLink->id;
    }

    private function unavailableOutcome(ShortLink $shortLink): ?string
    {
        if ($shortLink->isArchived()) {
            return AnalyticsService::OUTCOME_ARCHIVED;
        }

        if (! $shortLink->is_enabled) {
            return AnalyticsService::OUTCOME_DISABLED;
        }

        if ($shortLink->activates_at && $shortLink->activates_at->isFuture()) {
            return AnalyticsService::OUTCOME_SCHEDULED;
        }

        if ($shortLink->expires_at && $shortLink->expires_at->isPast()) {
            return AnalyticsService::OUTCOME_EXPIRED;
        }

        if ($shortLink->visit_limit !== null && $shortLink->successful_visits >= $shortLink->visit_limit) {
            return AnalyticsService::OUTCOME_VISIT_LIMIT_REACHED;
        }

        return null;
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

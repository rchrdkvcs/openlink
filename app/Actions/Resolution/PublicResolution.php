<?php

namespace App\Actions\Resolution;

use App\Actions\Analytics\RecordAnalytics;
use App\Actions\Domains\DomainLifecycle;
use App\Models\Domain;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\Analytics\Outcome;
use App\Services\QrCodes\QrCodeContent;
use App\Services\ResolutionContextFactory;
use App\Services\ShortLinks\ShortLinkLifecycle;
use App\Services\ShortLinks\ShortUrlCache;
use App\Services\ShortLinks\SmartRouting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PublicResolution
{
    private const PASSWORD_SESSION_PREFIX = 'openlink_protected_link_';

    public function __construct(
        private readonly RecordAnalytics $analytics,
        private readonly ResolutionContextFactory $contexts,
        private readonly DomainLifecycle $domains,
        private readonly ShortLinkLifecycle $lifecycle,
        private readonly SmartRouting $routing,
        private readonly ShortUrlCache $shortUrls,
        private readonly QrCodeContent $qrContent,
    ) {}

    public function resolve(Request $request, ResolutionEntry $entry): ResolutionResult
    {
        if ($entry->qrCode?->hasDirectPayload()) {
            return $this->directPayload($entry->qrCode);
        }

        $entry->qrCode?->loadMissing('shortLink.domain');
        $entry->shortLink?->loadMissing('domain');
        $knownLink = $entry->shortLink ?? $entry->qrCode?->shortLink;
        $domain = $knownLink
            ? $knownLink->domain
            : Domain::query()->where('hostname', $request->getHost())->first();

        if ($domain) {
            $this->domains->activateOnObservedTraffic($request, $domain);
        }

        if (! $domain || ! $domain->isUsable()) {
            return ResolutionResult::unavailable(Outcome::DOMAIN_UNAVAILABLE, $knownLink, $entry->qrCode);
        }

        $shortLink = $knownLink ?? $this->linkAt($domain, (string) $entry->slug);

        if (! $shortLink) {
            return ResolutionResult::unavailable(Outcome::NOT_FOUND);
        }

        $qrCode = $entry->qrCode ?? $this->submittedQrCode($shortLink, $entry->qrCodeId);

        if ($entry->isPasswordSubmission() && ! $this->unlock($request, $shortLink, (string) $entry->password)) {
            $this->analytics->record($request, $shortLink, $qrCode, Outcome::PASSWORD_FAILED);

            return ResolutionResult::passwordForm(Outcome::PASSWORD_FAILED, $shortLink, $qrCode);
        }

        return $this->resolveLink($request, $shortLink, $qrCode);
    }

    private function resolveLink(Request $request, ShortLink $shortLink, ?QrCode $qrCode): ResolutionResult
    {
        $unavailableOutcome = $this->lifecycle->unavailableOutcome($shortLink);

        if ($unavailableOutcome) {
            return $this->block($request, $shortLink, $qrCode, $unavailableOutcome);
        }

        if ($shortLink->hasPassword() && ! $request->session()->get($this->passwordSessionKey($shortLink))) {
            $this->analytics->record($request, $shortLink, $qrCode, Outcome::PASSWORD_REQUIRED);

            return ResolutionResult::passwordForm(Outcome::PASSWORD_REQUIRED, $shortLink, $qrCode);
        }

        if (! $this->lifecycle->reserveVisit($shortLink)) {
            $currentLink = ShortLink::query()->with('domain')->find($shortLink->id);

            if (! $currentLink) {
                return ResolutionResult::unavailable(Outcome::NOT_FOUND);
            }

            $outcome = $this->lifecycle->unavailableOutcome($currentLink) ?? Outcome::VISIT_LIMIT_REACHED;

            return $this->block($request, $currentLink, $qrCode, $outcome);
        }

        $context = $this->contexts->fromRequest($request);
        $decision = $this->routing->resolve($shortLink, $context);
        $this->analytics->record($request, $shortLink, $qrCode, Outcome::SUCCESS, $context, $decision);

        return ResolutionResult::redirect(Outcome::SUCCESS, $decision->destinationUrl, $shortLink, $qrCode);
    }

    private function block(Request $request, ShortLink $shortLink, ?QrCode $qrCode, string $outcome): ResolutionResult
    {
        $this->analytics->record($request, $shortLink, $qrCode, $outcome);

        return ResolutionResult::blocked($outcome, $shortLink, $qrCode);
    }

    private function unlock(Request $request, ShortLink $shortLink, string $password): bool
    {
        if (! $shortLink->password_hash || ! Hash::check($password, $shortLink->password_hash)) {
            return false;
        }

        $request->session()->put($this->passwordSessionKey($shortLink), true);

        return true;
    }

    private function directPayload(QrCode $qrCode): ResolutionResult
    {
        return $this->qrContent->shouldRedirect($qrCode)
            ? ResolutionResult::redirect(Outcome::SUCCESS, (string) $qrCode->content, qrCode: $qrCode)
            : ResolutionResult::qrPayload($qrCode);
    }

    private function linkAt(Domain $domain, string $slug): ?ShortLink
    {
        $shortLinkId = $this->shortUrls->shortLinkId($domain, $slug);

        return $shortLinkId ? ShortLink::query()->with('domain')->find($shortLinkId) : null;
    }

    private function submittedQrCode(ShortLink $shortLink, ?int $qrCodeId): ?QrCode
    {
        return $qrCodeId ? $shortLink->qrCodes()->whereKey($qrCodeId)->first() : null;
    }

    private function passwordSessionKey(ShortLink $shortLink): string
    {
        return self::PASSWORD_SESSION_PREFIX.$shortLink->id;
    }
}

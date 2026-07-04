<?php

namespace App\Services;

use App\Models\AnalyticsDailyAggregate;
use App\Models\AnalyticsTotal;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Jobs\RecordAnalyticsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnalyticsService
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_PASSWORD_FAILED = 'password_failed';
    public const OUTCOME_EXPIRED = 'expired';
    public const OUTCOME_DISABLED = 'disabled';
    public const OUTCOME_SCHEDULED = 'scheduled';
    public const OUTCOME_NOT_FOUND = 'not_found';
    public const OUTCOME_DOMAIN_UNAVAILABLE = 'domain_unavailable';
    public const OUTCOME_VISIT_LIMIT_REACHED = 'visit_limit_reached';
    public const OUTCOME_ARCHIVED = 'archived';

    public function queue(Request $request, ?ShortLink $shortLink, ?QrCode $qrCode, string $metric, string $outcome): void
    {
        RecordAnalyticsEvent::dispatch(
            shortLinkId: $shortLink?->id,
            qrCodeId: $qrCode?->id,
            metric: $metric,
            outcome: $outcome,
            referrerHost: $this->referrerHost($request),
            country: $this->country($request),
            deviceType: $this->deviceType($request),
            browser: $this->browser($request),
            os: $this->os($request),
        );
    }

    public function recordNow(
        ?int $shortLinkId,
        ?int $qrCodeId,
        string $metric,
        string $outcome,
        ?string $referrerHost,
        ?string $country,
        string $deviceType,
        string $browser,
        string $os,
    ): void
    {
        $shortLink = $shortLinkId ? ShortLink::query()->find($shortLinkId) : null;
        $qrCode = $qrCodeId ? QrCode::query()->with('shortLink')->find($qrCodeId) : null;
        $workspaceId = $shortLink?->workspace_id ?? $qrCode?->shortLink?->workspace_id;

        if (! $workspaceId) {
            return;
        }

        $dimensions = [
            'workspace_id' => $workspaceId,
            'short_link_id' => $shortLink?->id,
            'qr_code_id' => $qrCode?->id,
            'date' => now()->toDateString(),
            'metric' => $metric,
            'outcome' => $outcome,
            'referrer_host' => $referrerHost,
            'country' => $country,
            'device_type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
        ];

        $daily = AnalyticsDailyAggregate::query()->firstOrCreate($dimensions, ['count' => 0]);
        $daily->increment('count');

        $total = AnalyticsTotal::query()->firstOrCreate([
            'workspace_id' => $workspaceId,
            'short_link_id' => $shortLinkId,
            'qr_code_id' => $qrCodeId,
            'metric' => $metric,
            'outcome' => $outcome,
        ], ['count' => 0]);
        $total->increment('count');
    }

    private function referrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');

        if (! $referrer) {
            return null;
        }

        return parse_url($referrer, PHP_URL_HOST) ?: null;
    }

    private function deviceType(Request $request): string
    {
        $agent = Str::lower($request->userAgent() ?? '');

        return str_contains($agent, 'mobile')
            ? 'mobile'
            : (str_contains($agent, 'tablet') || str_contains($agent, 'ipad') ? 'tablet' : 'desktop');
    }

    private function country(Request $request): ?string
    {
        $country = $request->headers->get('CF-IPCountry')
            ?: $request->headers->get('X-Country-Code');

        return $country && preg_match('/^[A-Z]{2}$/', strtoupper($country))
            ? strtoupper($country)
            : null;
    }

    private function browser(Request $request): string
    {
        $agent = Str::lower($request->userAgent() ?? '');

        return match (true) {
            str_contains($agent, 'edg') => 'Edge',
            str_contains($agent, 'chrome') => 'Chrome',
            str_contains($agent, 'safari') => 'Safari',
            str_contains($agent, 'firefox') => 'Firefox',
            default => 'Other',
        };
    }

    private function os(Request $request): string
    {
        $agent = Str::lower($request->userAgent() ?? '');

        return match (true) {
            str_contains($agent, 'windows') => 'Windows',
            str_contains($agent, 'android') => 'Android',
            str_contains($agent, 'iphone') || str_contains($agent, 'ipad') => 'iOS',
            str_contains($agent, 'mac os') => 'macOS',
            str_contains($agent, 'linux') => 'Linux',
            default => 'Other',
        };
    }
}

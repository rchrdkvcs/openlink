<?php

namespace App\Services\Analytics;

use App\Jobs\RecordAnalyticsEvent;
use App\Models\AnalyticsEvent;
use App\Models\QrCode;
use App\Models\ShortLink;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Captures every request dimension synchronously (headers disappear once the
 * response is sent), then persists the event after the response by default so
 * recording works without a queue worker. Set OPENLINK_ANALYTICS_VIA_QUEUE=true
 * to move the write onto the queue on instances that run one.
 */
class AnalyticsRecorder
{
    public const METRIC_VISIT = 'visit';

    public const METRIC_SCAN = 'scan';

    public function __construct(
        private readonly UserAgentParser $userAgents,
        private readonly ReferrerClassifier $referrers,
    ) {}

    public function record(Request $request, ?ShortLink $shortLink, ?QrCode $qrCode, string $metric, string $outcome): void
    {
        $event = $this->capture($request, $shortLink, $qrCode, $metric, $outcome);

        if ($event === null) {
            return;
        }

        $job = new RecordAnalyticsEvent($event);

        config('openlink.analytics.via_queue')
            ? dispatch($job)
            : dispatch($job)->afterResponse();
    }

    /**
     * Writes a captured event, never letting a failure surface to the visitor.
     *
     * @param  array<string, mixed>  $event
     */
    public function persist(array $event): void
    {
        try {
            AnalyticsEvent::query()->create($event);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /** @return array<string, mixed>|null */
    public function capture(Request $request, ?ShortLink $shortLink, ?QrCode $qrCode, string $metric, string $outcome): ?array
    {
        $shortLink ??= $qrCode?->shortLink;
        $workspaceId = $shortLink?->workspace_id;

        if (! $workspaceId) {
            return null;
        }

        $agent = $this->userAgents->parse($request->userAgent());
        $referrer = $this->referrers->classify($request->headers->get('referer'));

        return [
            'workspace_id' => $workspaceId,
            'short_link_id' => $shortLink->id,
            'qr_code_id' => $qrCode?->id,
            'domain_id' => $shortLink->domain_id,
            'occurred_at' => now()->toDateTimeString(),
            'metric' => $metric,
            'outcome' => $outcome,
            'is_bot' => $agent['is_bot'],
            'visitor_hash' => $this->visitorHash($request),
            'referrer_host' => Str::limit($referrer['host'] ?? '', 250, '') ?: null,
            'referrer_channel' => $referrer['channel'],
            'country' => $this->country($request),
            'language' => $this->language($request),
            'device_type' => $agent['device_type'],
            'browser' => $agent['browser'],
            'os' => $agent['os'],
            ...$this->utm($request),
        ];
    }

    /**
     * A privacy-preserving visitor identifier: hashed from IP + user agent
     * with a salt that rotates daily, so visitors can be counted as unique
     * within a day but never tracked across days or identified.
     */
    private function visitorHash(Request $request): string
    {
        $salt = config('app.key').now()->toDateString();

        return substr(hash('sha256', $salt.'|'.$request->ip().'|'.$request->userAgent()), 0, 32);
    }

    private function country(Request $request): ?string
    {
        $headers = [
            'CF-IPCountry',            // Cloudflare
            'CloudFront-Viewer-Country', // AWS CloudFront
            'X-Vercel-IP-Country',     // Vercel
            'Fly-Client-IP-Country',   // Fly.io
            'X-Appengine-Country',     // Google App Engine
            'X-Country-Code',          // generic reverse proxies
            'X-Geo-Country',
        ];

        foreach ($headers as $header) {
            $value = strtoupper((string) $request->headers->get($header));

            // XX and T1 are Cloudflare's "unknown" and "Tor" placeholders.
            if (preg_match('/^[A-Z]{2}$/', $value) && ! in_array($value, ['XX', 'T1'], true)) {
                return $value;
            }
        }

        return null;
    }

    private function language(Request $request): ?string
    {
        $header = (string) $request->headers->get('Accept-Language');

        return preg_match('/^\s*([a-zA-Z]{2})\b/', $header, $matches)
            ? strtolower($matches[1])
            : null;
    }

    /** @return array<string, ?string> */
    private function utm(Request $request): array
    {
        $params = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            $value = $request->query($key);
            $value = is_string($value) ? trim($value) : '';

            $params[$key] = $value === '' ? null : Str::limit($value, 250, '');
        }

        return $params;
    }
}

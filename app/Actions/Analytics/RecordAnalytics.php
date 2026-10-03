<?php

namespace App\Actions\Analytics;

use App\Enums\AnalyticsMetric;
use App\Jobs\RecordAnalyticsEvent;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\ResolutionContext;
use App\Services\ResolutionContextFactory;
use App\Services\RoutingDecision;
use Illuminate\Http\Request;

class RecordAnalytics
{
    public function __construct(private readonly ResolutionContextFactory $contexts) {}

    public function record(
        Request $request,
        ShortLink $shortLink,
        ?QrCode $qrCode,
        string $outcome,
        ?ResolutionContext $context = null,
        ?RoutingDecision $decision = null,
    ): void {
        if (! $shortLink->workspace_id) {
            return;
        }

        $context ??= $this->contexts->fromRequest($request);
        $job = new RecordAnalyticsEvent($this->event($shortLink, $qrCode, $outcome, $context, $decision));

        config('openlink.analytics.via_queue')
            ? dispatch($job)
            : dispatch($job)->afterResponse();
    }

    private function event(
        ShortLink $shortLink,
        ?QrCode $qrCode,
        string $outcome,
        ResolutionContext $context,
        ?RoutingDecision $decision,
    ): array {
        $dimensions = $context->analyticsDimensions();

        return [
            'workspace_id' => $shortLink->workspace_id,
            'short_link_id' => $shortLink->id,
            'qr_code_id' => $qrCode?->id,
            'domain_id' => $shortLink->domain_id,
            'routing_rule_id' => $decision?->rule?->id,
            'routing_variant_id' => $decision?->variant?->id,
            'occurred_at' => $context->occurredAt->toDateTimeString(),
            'metric' => AnalyticsMetric::forEntry($qrCode)->value,
            'outcome' => $outcome,
            'is_bot' => $dimensions['is_bot'],
            'visitor_hash' => $context->visitorHash,
            'referrer_host' => $dimensions['referrer_host'],
            'referrer_channel' => $dimensions['referrer_channel'],
            'country' => $dimensions['country'],
            'language' => $dimensions['language'],
            'device_type' => $dimensions['device_type'],
            'browser' => $dimensions['browser'],
            'os' => $dimensions['os'],
            'utm_source' => $dimensions['utm_source'],
            'utm_medium' => $dimensions['utm_medium'],
            'utm_campaign' => $dimensions['utm_campaign'],
            'utm_term' => $dimensions['utm_term'],
            'utm_content' => $dimensions['utm_content'],
        ];
    }
}

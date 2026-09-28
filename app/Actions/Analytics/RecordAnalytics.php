<?php

namespace App\Actions\Analytics;

use App\Jobs\RecordAnalyticsEvent;
use App\Models\AnalyticsEvent;
use App\Models\QrCode;
use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Services\ResolutionContext;
use App\Services\ResolutionContextFactory;
use Illuminate\Http\Request;

class RecordAnalytics
{
    public const METRIC_VISIT = 'visit';

    public const METRIC_SCAN = 'scan';

    public function __construct(private readonly ResolutionContextFactory $contexts) {}

    public function record(
        Request $request,
        ?ShortLink $shortLink,
        ?QrCode $qrCode,
        string $metric,
        string $outcome,
        ?ResolutionContext $context = null,
        ?RoutingRule $routingRule = null,
        ?RoutingVariant $routingVariant = null,
    ): void {
        $event = $this->capture($request, $shortLink, $qrCode, $metric, $outcome, $context, $routingRule, $routingVariant);

        if ($event === null) {
            return;
        }

        $job = new RecordAnalyticsEvent($event);

        config('openlink.analytics.via_queue')
            ? dispatch($job)
            : dispatch($job)->afterResponse();
    }

    public function persist(array $event): void
    {
        try {
            AnalyticsEvent::query()->create($event);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function capture(
        Request $request,
        ?ShortLink $shortLink,
        ?QrCode $qrCode,
        string $metric,
        string $outcome,
        ?ResolutionContext $context = null,
        ?RoutingRule $routingRule = null,
        ?RoutingVariant $routingVariant = null,
    ): ?array {
        $shortLink ??= $qrCode?->shortLink;
        $workspaceId = $shortLink?->workspace_id;

        if (! $workspaceId) {
            return null;
        }

        $context ??= $this->contexts->fromRequest($request);
        $dimensions = $context->analyticsDimensions();

        return [
            'workspace_id' => $workspaceId,
            'short_link_id' => $shortLink->id,
            'qr_code_id' => $qrCode?->id,
            'domain_id' => $shortLink->domain_id,
            'routing_rule_id' => $routingRule?->id,
            'routing_variant_id' => $routingVariant?->id,
            'occurred_at' => $context->occurredAt->toDateTimeString(),
            'metric' => $metric,
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

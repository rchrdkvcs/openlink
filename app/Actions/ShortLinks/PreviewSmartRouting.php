<?php

namespace App\Actions\ShortLinks;

use App\Enums\LinkStatus;
use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Services\ResolutionContextFactory;
use App\Services\ShortLinks\Routing\RoutingTrace;
use App\Services\ShortLinks\Routing\RuleTrace;
use App\Services\ShortLinks\Routing\VariantPicker;
use App\Services\ShortLinks\ShortLinkLifecycle;
use App\Services\ShortLinks\SmartRouting;
use Carbon\CarbonImmutable;

class PreviewSmartRouting
{
    public const SOURCE_SAVED = 'saved';

    public const SOURCE_DRAFT = 'draft';

    public function __construct(
        private readonly ResolutionContextFactory $contexts,
        private readonly SmartRouting $routing,
        private readonly VariantPicker $variants,
        private readonly ShortLinkLifecycle $lifecycle,
    ) {}

    public function handle(ShortLink $shortLink, array $input): array
    {
        $timezone = $input['context']['timezone'];
        $occurredAt = filled($input['context']['occurred_at'] ?? null)
            ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $input['context']['occurred_at'], $timezone)
            : CarbonImmutable::now($timezone);
        $context = $this->contexts->simulated($input['context'], $occurredAt, (string) ($input['context']['seed'] ?? ''));
        $trace = $input['source'] === self::SOURCE_DRAFT
            ? $this->routing->traceDraft($shortLink, $input['routing_rules'] ?? [], $input['destination_url'], $context)
            : $this->routing->trace($shortLink, $context);

        return [
            'source' => $input['source'],
            'context' => [
                'occurred_at' => $occurredAt->utc()->toIso8601String(),
                'local_time' => $occurredAt->toIso8601String(),
                'timezone' => $timezone,
                'visitor_hash' => $context->visitorHash,
                'dimensions' => $context->analyticsDimensions(),
            ],
            'rules' => array_map(fn (RuleTrace $rule, int $index) => $this->rule($rule, $index), $trace->rules, array_keys($trace->rules)),
            'decision' => $this->decision($trace),
            'availability' => $this->availability($shortLink),
        ];
    }

    private function rule(RuleTrace $trace, int $index): array
    {
        $rule = $trace->rule;
        $splitTest = $rule->type === RoutingRule::TYPE_SPLIT_TEST;
        $totalWeight = $splitTest ? (int) $this->variants->eligible($rule)->sum('weight') : null;

        return [
            'index' => $index,
            'id' => $rule->id,
            'name' => $rule->name,
            'type' => $rule->type,
            'match_mode' => $rule->match_mode,
            'status' => $trace->status,
            'destination_url' => $rule->destination_url,
            'conditions' => array_map(fn (array $result) => [
                'type' => $result['condition']['type'] ?? null,
                'operator' => $result['condition']['operator'] ?? null,
                'value' => $result['condition']['value'] ?? null,
                'timezone' => $result['condition']['timezone'] ?? null,
                'matched' => $result['matched'],
            ], $trace->conditions),
            'slot' => $trace->slot,
            'total_weight' => $totalWeight,
            'variants' => $splitTest
                ? $rule->variants->map(fn (RoutingVariant $variant) => $this->variant($variant, $trace, $totalWeight))->values()->all()
                : [],
        ];
    }

    private function variant(RoutingVariant $variant, RuleTrace $trace, int $totalWeight): array
    {
        $eligible = $this->variants->isEligible($variant);

        return [
            'id' => $variant->id,
            'name' => $variant->name,
            'destination_url' => $variant->destination_url,
            'weight' => (int) $variant->weight,
            'is_enabled' => (bool) $variant->is_enabled,
            'eligible' => $eligible,
            'share' => $eligible && $totalWeight > 0 ? round($variant->weight / $totalWeight * 100, 1) : 0.0,
            'selected' => $trace->variant === $variant,
        ];
    }

    private function decision(RoutingTrace $trace): array
    {
        $decision = $trace->decision;
        $index = array_key_first(array_filter($trace->rules, fn (RuleTrace $rule) => $rule->matched()));

        return [
            'rule_index' => $index,
            'rule_id' => $decision->rule?->id,
            'rule_name' => $decision->rule?->name,
            'variant_id' => $decision->variant?->id,
            'variant_name' => $decision->variant?->name,
            'destination_url' => $decision->destinationUrl,
            'is_default' => $decision->rule === null,
        ];
    }

    private function availability(ShortLink $shortLink): array
    {
        $shortLink->loadMissing('domain');
        $status = $this->lifecycle->statusOf($shortLink);
        $domainUsable = $shortLink->domain?->isUsable() ?? false;

        return [
            'status' => $status->value,
            'domain' => $shortLink->domain?->hostname,
            'domain_usable' => $domainUsable,
            'password_protected' => $shortLink->hasPassword(),
            'available' => $status === LinkStatus::Active && $domainUsable,
        ];
    }
}

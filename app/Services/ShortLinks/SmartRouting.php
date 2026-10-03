<?php

namespace App\Services\ShortLinks;

use App\Models\RoutingRule;
use App\Models\ShortLink;
use App\Services\ResolutionContext;
use App\Services\RoutingDecision;
use App\Services\ShortLinks\Routing\ConditionMatcher;
use App\Services\ShortLinks\Routing\RoutingEditorSchema;
use App\Services\ShortLinks\Routing\RoutingRulesStore;
use App\Services\ShortLinks\Routing\RoutingRulesValidator;
use App\Services\ShortLinks\Routing\VariantPicker;

class SmartRouting
{
    public function __construct(
        private readonly ConditionMatcher $matcher,
        private readonly VariantPicker $variants,
        private readonly RoutingRulesValidator $validator,
        private readonly RoutingRulesStore $store,
        private readonly RoutingEditorSchema $schema,
    ) {}

    public function resolve(ShortLink $shortLink, ResolutionContext $context): RoutingDecision
    {
        $shortLink->loadMissing(['routingRules.variants']);

        foreach ($shortLink->routingRules as $rule) {
            if (! $rule->is_enabled || ! $this->matcher->matches($rule, $context)) {
                continue;
            }

            if ($rule->type === RoutingRule::TYPE_SPLIT_TEST) {
                $variant = $this->variants->pick($rule, $context->visitorHash);

                if ($variant) {
                    return new RoutingDecision($variant->destination_url, $rule, $variant);
                }

                continue;
            }

            if (filled($rule->destination_url)) {
                return new RoutingDecision($rule->destination_url, $rule);
            }
        }

        return new RoutingDecision($shortLink->destination_url);
    }

    public function sync(ShortLink $shortLink, array $rules): void
    {
        $this->validator->validate(ShortUrlAddress::of($shortLink), $rules);
        $this->store->replace($shortLink, $rules);
    }

    public function editorPayload(): array
    {
        return $this->schema->payload();
    }
}

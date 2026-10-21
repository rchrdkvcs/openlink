<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;
use App\Services\ResolutionContext;
use App\Services\RoutingDecision;

class RoutingEvaluator
{
    public function __construct(
        private readonly ConditionMatcher $matcher,
        private readonly VariantPicker $variants,
    ) {}

    public function evaluate(iterable $rules, string $defaultDestination, ResolutionContext $context): RoutingTrace
    {
        $traces = [];
        $decision = null;

        foreach ($rules as $rule) {
            $trace = $decision ? new RuleTrace($rule, RuleTrace::NOT_REACHED) : $this->trace($rule, $context);
            $traces[] = $trace;

            if ($trace->matched()) {
                $decision = new RoutingDecision($trace->destinationUrl(), $rule, $trace->variant);
            }
        }

        return new RoutingTrace($traces, $decision ?? new RoutingDecision($defaultDestination));
    }

    private function trace(RoutingRule $rule, ResolutionContext $context): RuleTrace
    {
        if (! $rule->is_enabled) {
            return new RuleTrace($rule, RuleTrace::DISABLED);
        }

        $conditions = $this->matcher->results($rule, $context);

        if (! $this->matcher->combine($rule, $conditions)) {
            return new RuleTrace($rule, RuleTrace::NOT_MATCHED, $conditions);
        }

        if ($rule->type === RoutingRule::TYPE_SPLIT_TEST) {
            $variant = $this->variants->pick($rule, $context->visitorHash);
            $status = $variant ? RuleTrace::MATCHED : RuleTrace::SKIPPED;

            return new RuleTrace($rule, $status, $conditions, $this->variants->slot($rule, $context->visitorHash), $variant);
        }

        return new RuleTrace($rule, filled($rule->destination_url) ? RuleTrace::MATCHED : RuleTrace::SKIPPED, $conditions);
    }
}

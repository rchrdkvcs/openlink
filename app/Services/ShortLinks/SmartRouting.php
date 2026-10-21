<?php

namespace App\Services\ShortLinks;

use App\Models\ShortLink;
use App\Services\ResolutionContext;
use App\Services\RoutingDecision;
use App\Services\ShortLinks\Routing\RoutingEditorSchema;
use App\Services\ShortLinks\Routing\RoutingEvaluator;
use App\Services\ShortLinks\Routing\RoutingRuleDrafts;
use App\Services\ShortLinks\Routing\RoutingRulesStore;
use App\Services\ShortLinks\Routing\RoutingRulesValidator;
use App\Services\ShortLinks\Routing\RoutingTrace;

class SmartRouting
{
    public function __construct(
        private readonly RoutingEvaluator $evaluator,
        private readonly RoutingRuleDrafts $drafts,
        private readonly RoutingRulesValidator $validator,
        private readonly RoutingRulesStore $store,
        private readonly RoutingEditorSchema $schema,
    ) {}

    public function resolve(ShortLink $shortLink, ResolutionContext $context): RoutingDecision
    {
        return $this->trace($shortLink, $context)->decision;
    }

    public function trace(ShortLink $shortLink, ResolutionContext $context): RoutingTrace
    {
        $shortLink->loadMissing(['routingRules.variants']);

        return $this->evaluator->evaluate($shortLink->routingRules, $shortLink->destination_url, $context);
    }

    public function traceDraft(ShortLink $shortLink, array $rules, string $defaultDestination, ResolutionContext $context): RoutingTrace
    {
        $this->validator->validate(ShortUrlAddress::of($shortLink), $rules);

        return $this->evaluator->evaluate($this->drafts->unsaved($rules), $defaultDestination, $context);
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

<?php

namespace App\Actions\Routing;

use App\Models\RoutingRule;
use App\Models\ShortLink;
use App\Services\Routing\SmartRoutingSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncRoutingRules
{
    public function __construct(private readonly SmartRoutingSchema $schema) {}

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    public function handle(ShortLink $shortLink, array $rules): void
    {
        $this->validate($shortLink, $rules);

        $shortLink->routingRules()->delete();

        foreach (array_values($rules) as $index => $ruleData) {
            $rule = $shortLink->routingRules()->create([
                'name' => filled($ruleData['name'] ?? null) ? $ruleData['name'] : 'Routing rule '.($index + 1),
                'type' => $ruleData['type'] ?? RoutingRule::TYPE_CONDITIONAL,
                'position' => $index + 1,
                'is_enabled' => $ruleData['is_enabled'] ?? true,
                'match_mode' => $ruleData['match_mode'] ?? RoutingRule::MATCH_ALL,
                'conditions_version' => 1,
                'conditions' => array_values($ruleData['conditions'] ?? []),
                'destination_url' => ($ruleData['type'] ?? RoutingRule::TYPE_CONDITIONAL) === RoutingRule::TYPE_CONDITIONAL
                    ? ($ruleData['destination_url'] ?? null)
                    : null,
            ]);

            if (($ruleData['type'] ?? RoutingRule::TYPE_CONDITIONAL) !== RoutingRule::TYPE_SPLIT_TEST) {
                continue;
            }

            foreach (array_values($ruleData['variants'] ?? []) as $variantIndex => $variantData) {
                $rule->variants()->create([
                    'name' => filled($variantData['name'] ?? null) ? $variantData['name'] : 'Variant '.($variantIndex + 1),
                    'position' => $variantIndex + 1,
                    'is_enabled' => $variantData['is_enabled'] ?? true,
                    'destination_url' => $variantData['destination_url'],
                    'weight' => $variantData['weight'] ?? 50,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rules
     */
    private function validate(ShortLink $shortLink, array $rules): void
    {
        Validator::make(['routing_rules' => $rules], $this->schema->validationRules())->validate();

        foreach (array_values($rules) as $index => $rule) {
            $enabled = $rule['is_enabled'] ?? true;
            $type = $rule['type'] ?? RoutingRule::TYPE_CONDITIONAL;

            if (! $enabled) {
                continue;
            }

            if ($type === RoutingRule::TYPE_CONDITIONAL) {
                if (! filled($rule['destination_url'] ?? null)) {
                    throw ValidationException::withMessages(["routing_rules.$index.destination_url" => 'A conditional routing rule needs a destination URL.']);
                }

                $this->assertNoLoop($shortLink, $rule['destination_url'], "routing_rules.$index.destination_url");

                continue;
            }

            $activeVariants = collect($rule['variants'] ?? [])
                ->filter(fn (array $variant) => ($variant['is_enabled'] ?? true) && (int) ($variant['weight'] ?? 50) > 0);

            if ($activeVariants->count() < 2) {
                throw ValidationException::withMessages(["routing_rules.$index.variants" => 'An active split test needs at least two active variants with positive weights.']);
            }

            foreach ($activeVariants as $variantIndex => $variant) {
                if (! filled($variant['destination_url'] ?? null)) {
                    throw ValidationException::withMessages(["routing_rules.$index.variants.$variantIndex.destination_url" => 'An active variant needs a destination URL.']);
                }

                $this->assertNoLoop($shortLink, $variant['destination_url'], "routing_rules.$index.variants.$variantIndex.destination_url");
            }
        }
    }

    private function assertNoLoop(ShortLink $shortLink, string $destinationUrl, string $field): void
    {
        $shortLink->loadMissing('domain');
        $targetHost = parse_url($destinationUrl, PHP_URL_HOST);
        $targetPath = trim((string) parse_url($destinationUrl, PHP_URL_PATH), '/');

        if ($shortLink->domain && $targetHost === $shortLink->domain->hostname && $targetPath === trim($shortLink->slug, '/')) {
            throw ValidationException::withMessages([$field => 'Destination URL cannot point to the same short URL.']);
        }
    }
}

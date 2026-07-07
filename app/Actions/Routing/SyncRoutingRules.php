<?php

namespace App\Actions\Routing;

use App\Models\RoutingRule;
use App\Models\ShortLink;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SyncRoutingRules
{
    private const CONDITION_TYPES = [
        'country',
        'language',
        'device_type',
        'browser',
        'operating_system',
        'referrer_host',
        'referrer_channel',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'date_time',
        'day_of_week',
        'time_of_day',
    ];

    private const OPERATORS = [
        'is',
        'is_not',
        'contains',
        'does_not_contain',
        'starts_with',
        'ends_with',
        'is_empty',
        'is_not_empty',
        'before',
        'after',
        'between',
    ];

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
        Validator::make(['routing_rules' => $rules], $this->rules())->validate();

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

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'routing_rules' => ['array', 'max:50'],
            'routing_rules.*.name' => ['nullable', 'string', 'max:120'],
            'routing_rules.*.type' => ['nullable', Rule::in([RoutingRule::TYPE_CONDITIONAL, RoutingRule::TYPE_SPLIT_TEST])],
            'routing_rules.*.is_enabled' => ['nullable', 'boolean'],
            'routing_rules.*.match_mode' => ['nullable', Rule::in([RoutingRule::MATCH_ALL, RoutingRule::MATCH_ANY])],
            'routing_rules.*.conditions' => ['nullable', 'array', 'max:20'],
            'routing_rules.*.conditions.*.type' => ['required_with:routing_rules.*.conditions', Rule::in(self::CONDITION_TYPES)],
            'routing_rules.*.conditions.*.operator' => ['required_with:routing_rules.*.conditions', Rule::in(self::OPERATORS)],
            'routing_rules.*.conditions.*.value' => ['nullable'],
            'routing_rules.*.conditions.*.timezone' => ['nullable', 'timezone'],
            'routing_rules.*.destination_url' => ['nullable', 'url:http,https'],
            'routing_rules.*.variants' => ['nullable', 'array', 'max:20'],
            'routing_rules.*.variants.*.name' => ['nullable', 'string', 'max:120'],
            'routing_rules.*.variants.*.is_enabled' => ['nullable', 'boolean'],
            'routing_rules.*.variants.*.destination_url' => ['required_with:routing_rules.*.variants', 'url:http,https'],
            'routing_rules.*.variants.*.weight' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
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

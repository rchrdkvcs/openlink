<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;
use App\Services\ShortLinks\ShortUrlAddress;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoutingRulesValidator
{
    public static function inputRules(): array
    {
        return [
            'routing_rules' => ['nullable', 'array', 'max:50'],
            'routing_rules.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'routing_rules.*.name' => ['nullable', 'string', 'max:120'],
            'routing_rules.*.type' => ['nullable', Rule::in([RoutingRule::TYPE_CONDITIONAL, RoutingRule::TYPE_SPLIT_TEST])],
            'routing_rules.*.is_enabled' => ['nullable', 'boolean'],
            'routing_rules.*.match_mode' => ['nullable', Rule::in([RoutingRule::MATCH_ALL, RoutingRule::MATCH_ANY])],
            'routing_rules.*.conditions' => ['nullable', 'array', 'max:20'],
            'routing_rules.*.conditions.*.type' => ['required_with:routing_rules.*.conditions', Rule::in(RoutingEditorSchema::conditionTypes())],
            'routing_rules.*.conditions.*.operator' => ['required_with:routing_rules.*.conditions', Rule::in(RoutingEditorSchema::operators())],
            'routing_rules.*.conditions.*.value' => ['nullable'],
            'routing_rules.*.conditions.*.timezone' => ['nullable', 'timezone'],
            'routing_rules.*.destination_url' => ['nullable', 'url:http,https'],
            'routing_rules.*.variants' => ['nullable', 'array', 'max:20'],
            'routing_rules.*.variants.*.id' => ['nullable', 'integer', 'min:1', 'distinct'],
            'routing_rules.*.variants.*.name' => ['nullable', 'string', 'max:120'],
            'routing_rules.*.variants.*.is_enabled' => ['nullable', 'boolean'],
            'routing_rules.*.variants.*.destination_url' => ['required_with:routing_rules.*.variants', 'url:http,https'],
            'routing_rules.*.variants.*.weight' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ];
    }

    public function validate(ShortUrlAddress $address, array $rules): void
    {
        foreach (array_values($rules) as $index => $rule) {
            if (! ($rule['is_enabled'] ?? true)) {
                continue;
            }

            if (($rule['type'] ?? RoutingRule::TYPE_CONDITIONAL) === RoutingRule::TYPE_CONDITIONAL) {
                $this->validateConditional($address, $rule, "routing_rules.$index");
            } else {
                $this->validateSplitTest($address, $rule, "routing_rules.$index");
            }
        }
    }

    private function validateConditional(ShortUrlAddress $address, array $rule, string $field): void
    {
        if (! filled($rule['destination_url'] ?? null)) {
            $this->fail("$field.destination_url", 'A conditional routing rule needs a destination URL.');
        }

        $address->rejectLoop($rule['destination_url'], "$field.destination_url");
    }

    private function validateSplitTest(ShortUrlAddress $address, array $rule, string $field): void
    {
        $activeVariants = collect($rule['variants'] ?? [])
            ->filter(fn (array $variant) => ($variant['is_enabled'] ?? true) && (int) ($variant['weight'] ?? 50) > 0);

        if ($activeVariants->count() < 2) {
            $this->fail("$field.variants", 'An active split test needs at least two active variants with positive weights.');
        }

        foreach ($activeVariants as $index => $variant) {
            if (! filled($variant['destination_url'] ?? null)) {
                $this->fail("$field.variants.$index.destination_url", 'An active variant needs a destination URL.');
            }

            $address->rejectLoop($variant['destination_url'], "$field.variants.$index.destination_url");
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

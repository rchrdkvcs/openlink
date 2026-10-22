<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use Illuminate\Database\Eloquent\Collection;

class RoutingRuleDrafts
{
    public static function enabled(array $data): bool
    {
        return filter_var($data['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }

    public function ruleAttributes(array $data, int $index): array
    {
        $type = $data['type'] ?? RoutingRule::TYPE_CONDITIONAL;

        return [
            'name' => filled($data['name'] ?? null) ? $data['name'] : 'Routing rule '.($index + 1),
            'type' => $type,
            'position' => $index + 1,
            'is_enabled' => self::enabled($data),
            'match_mode' => $data['match_mode'] ?? RoutingRule::MATCH_ALL,
            'conditions_version' => 1,
            'conditions' => array_values($data['conditions'] ?? []),
            'destination_url' => $type === RoutingRule::TYPE_CONDITIONAL ? ($data['destination_url'] ?? null) : null,
        ];
    }

    public function variantAttributes(array $data, int $position): array
    {
        return [
            'name' => filled($data['name'] ?? null) ? $data['name'] : 'Variant '.($position + 1),
            'position' => $position + 1,
            'is_enabled' => self::enabled($data),
            'destination_url' => $data['destination_url'],
            'weight' => $data['weight'] ?? 50,
        ];
    }

    public function unsaved(array $rules): Collection
    {
        return new Collection(collect(array_values($rules))->map(function (array $data, int $index) {
            $rule = new RoutingRule($this->ruleAttributes($data, $index));
            $variants = $rule->type === RoutingRule::TYPE_SPLIT_TEST ? array_values($data['variants'] ?? []) : [];

            return $rule->setRelation('variants', new Collection(collect($variants)->map(
                fn (array $variant, int $position) => new RoutingVariant($this->variantAttributes($variant, $position)),
            )->all()));
        })->all());
    }
}

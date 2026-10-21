<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;
use App\Models\ShortLink;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoutingRulesStore
{
    public function __construct(private readonly RoutingRuleDrafts $drafts) {}

    public function replace(ShortLink $shortLink, array $rules): void
    {
        DB::transaction(function () use ($shortLink, $rules): void {
            ShortLink::query()->whereKey($shortLink->id)->lockForUpdate()->firstOrFail();

            $this->replaceChildren(
                $shortLink->routingRules(),
                $shortLink->routingRules()->with('variants')->get(),
                $rules,
                'routing_rules',
                'This routing rule does not belong to the Short Link.',
                fn (array $data, int $index) => $this->drafts->ruleAttributes($data, $index),
                fn (RoutingRule $rule, array $data, int $index) => $this->replaceVariants($rule, $data, $index),
            );

            $shortLink->unsetRelation('routingRules');
        });
    }

    private function replaceVariants(RoutingRule $rule, array $data, int $index): void
    {
        if ($rule->type !== RoutingRule::TYPE_SPLIT_TEST) {
            $rule->variants()->delete();

            return;
        }

        $this->replaceChildren(
            $rule->variants(),
            $rule->variants,
            $data['variants'] ?? [],
            "routing_rules.$index.variants",
            'This variant does not belong to the routing rule.',
            fn (array $variant, int $position) => $this->drafts->variantAttributes($variant, $position),
        );
    }

    private function replaceChildren(
        HasMany $relation,
        Collection $existing,
        array $items,
        string $field,
        string $foreignMessage,
        callable $attributes,
        ?callable $afterSave = null,
    ): void {
        $existing = $existing->keyBy('id');
        $retained = [];

        foreach (array_values($items) as $index => $data) {
            $id = $data['id'] ?? null;
            $model = $id === null ? null : $existing->get((int) $id);

            if ($id !== null && $model === null) {
                throw ValidationException::withMessages(["$field.$index.id" => $foreignMessage]);
            }

            $model = $this->save($relation, $model, $attributes($data, $index));

            if ($afterSave !== null) {
                $afterSave($model, $data, $index);
            }

            $retained[] = $model->id;
        }

        $relation->whereNotIn('id', $retained)->delete();
    }

    private function save(HasMany $relation, ?Model $model, array $attributes): Model
    {
        if ($model === null) {
            return $relation->create($attributes);
        }

        $model->update($attributes);

        return $model;
    }
}

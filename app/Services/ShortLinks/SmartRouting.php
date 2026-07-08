<?php

namespace App\Services\ShortLinks;

use App\Models\RoutingRule;
use App\Models\RoutingVariant;
use App\Models\ShortLink;
use App\Services\ResolutionContext;
use App\Services\RoutingDecision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SmartRouting
{
    private const LABELS = [
        'country' => 'Country',
        'language' => 'Language',
        'device_type' => 'Device',
        'browser' => 'Browser',
        'operating_system' => 'OS',
        'referrer_host' => 'Referrer host',
        'referrer_channel' => 'Referrer channel',
        'utm_source' => 'UTM source',
        'utm_medium' => 'UTM medium',
        'utm_campaign' => 'UTM campaign',
        'utm_term' => 'UTM term',
        'utm_content' => 'UTM content',
        'date_time' => 'Date/time',
        'day_of_week' => 'Day',
        'time_of_day' => 'Time',
    ];

    private const SCALAR_OPERATORS = [
        'is' => 'is',
        'is_not' => 'is not',
        'contains' => 'contains',
        'does_not_contain' => 'does not contain',
        'starts_with' => 'starts with',
        'ends_with' => 'ends with',
        'is_empty' => 'is empty',
        'is_not_empty' => 'is not empty',
    ];

    private const TIME_OPERATORS = [
        'before' => 'before',
        'after' => 'after',
        'between' => 'between',
    ];

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

    /** @return array<string, mixed> */
    public function editorPayload(): array
    {
        return [
            'conditionTypes' => collect(self::CONDITION_TYPES)
                ->map(fn (string $type) => ['value' => $type, 'label' => self::LABELS[$type]])
                ->values()
                ->all(),
            'operators' => [
                'scalar' => $this->options(self::SCALAR_OPERATORS),
                'time' => $this->options(self::TIME_OPERATORS),
            ],
            'valueOptions' => [
                'day_of_week' => $this->plainOptions(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                'device_type' => $this->plainOptions(['mobile', 'desktop', 'tablet', 'bot']),
                'referrer_channel' => $this->plainOptions(['direct', 'search', 'social', 'video', 'email', 'messaging', 'ai', 'referral']),
            ],
            'defaults' => [
                'device_type' => 'mobile',
                'referrer_channel' => 'social',
                'country' => 'FR',
                'language' => 'fr',
            ],
            'presets' => [
                ['kind' => 'country', 'label' => 'Country', 'description' => 'Send visitors to a destination by country.', 'conditionType' => 'country', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
                ['kind' => 'device', 'label' => 'Device', 'description' => 'Route mobile, desktop, tablet, or bot traffic.', 'conditionType' => 'device_type', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
                ['kind' => 'campaign', 'label' => 'Campaign', 'description' => 'Match UTM campaign parameters.', 'conditionType' => 'utm_campaign', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
                ['kind' => 'time', 'label' => 'Time', 'description' => 'Use a time window with an explicit timezone.', 'conditionType' => 'time_of_day', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
                ['kind' => 'split', 'label' => 'Split test', 'description' => 'Split traffic between weighted variants.', 'conditionType' => 'custom', 'ruleType' => RoutingRule::TYPE_SPLIT_TEST],
                ['kind' => 'custom', 'label' => 'Custom', 'description' => 'Start from a blank rule.', 'conditionType' => 'custom', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
            ],
        ];
    }

    /** @param array<int, array<string, mixed>> $rules */
    public function sync(ShortLink $shortLink, array $rules): void
    {
        $this->validate($shortLink, $rules);

        $shortLink->routingRules()->delete();

        foreach (array_values($rules) as $index => $ruleData) {
            $type = $ruleData['type'] ?? RoutingRule::TYPE_CONDITIONAL;
            $rule = $shortLink->routingRules()->create([
                'name' => filled($ruleData['name'] ?? null) ? $ruleData['name'] : 'Routing rule '.($index + 1),
                'type' => $type,
                'position' => $index + 1,
                'is_enabled' => $ruleData['is_enabled'] ?? true,
                'match_mode' => $ruleData['match_mode'] ?? RoutingRule::MATCH_ALL,
                'conditions_version' => 1,
                'conditions' => array_values($ruleData['conditions'] ?? []),
                'destination_url' => $type === RoutingRule::TYPE_CONDITIONAL
                    ? ($ruleData['destination_url'] ?? null)
                    : null,
            ]);

            if ($type !== RoutingRule::TYPE_SPLIT_TEST) {
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

    public function resolve(ShortLink $shortLink, ResolutionContext $context): RoutingDecision
    {
        $shortLink->loadMissing(['routingRules.variants']);

        foreach ($shortLink->routingRules as $rule) {
            if (! $rule->is_enabled || ! $this->matches($rule, $context)) {
                continue;
            }

            if ($rule->type === RoutingRule::TYPE_SPLIT_TEST) {
                $variant = $this->variantFor($rule, $context);

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

    /** @param array<int, array<string, mixed>> $rules */
    private function validate(ShortLink $shortLink, array $rules): void
    {
        Validator::make(['routing_rules' => $rules], $this->validationRules())->validate();

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
    private function validationRules(): array
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

    private function matches(RoutingRule $rule, ResolutionContext $context): bool
    {
        $conditions = collect($rule->conditions ?? [])->filter(fn ($condition) => is_array($condition));

        if ($conditions->isEmpty()) {
            return true;
        }

        $results = $conditions->map(fn (array $condition) => $this->conditionMatches($condition, $context));

        return $rule->match_mode === RoutingRule::MATCH_ANY
            ? $results->contains(true)
            : $results->every(fn (bool $result) => $result);
    }

    /** @param array<string, mixed> $condition */
    private function conditionMatches(array $condition, ResolutionContext $context): bool
    {
        $type = (string) ($condition['type'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'is');

        return match ($type) {
            'date_time' => $this->dateTimeMatches($condition, $context),
            'day_of_week' => $this->dayOfWeekMatches($condition, $context),
            'time_of_day' => $this->timeOfDayMatches($condition, $context),
            default => $this->scalarMatches($context->value($this->contextKey($type)), $operator, $condition['value'] ?? null),
        };
    }

    private function contextKey(string $conditionType): string
    {
        return match ($conditionType) {
            'operating_system' => 'os',
            default => $conditionType,
        };
    }

    private function scalarMatches(mixed $actual, string $operator, mixed $expected): bool
    {
        $isEmpty = $actual === null || $actual === '';

        if ($operator === 'is_empty') {
            return $isEmpty;
        }

        if ($operator === 'is_not_empty') {
            return ! $isEmpty;
        }

        if ($isEmpty) {
            return false;
        }

        $actual = mb_strtolower((string) $actual);
        $expectedValues = is_array($expected) ? $expected : [$expected];
        $expectedValues = collect($expectedValues)
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->filter(fn (string $value) => $value !== '')
            ->values();

        if ($expectedValues->isEmpty()) {
            return false;
        }

        return match ($operator) {
            'is' => $expectedValues->contains($actual),
            'is_not' => ! $expectedValues->contains($actual),
            'contains' => $expectedValues->contains(fn (string $value) => str_contains($actual, $value)),
            'does_not_contain' => $expectedValues->every(fn (string $value) => ! str_contains($actual, $value)),
            'starts_with' => $expectedValues->contains(fn (string $value) => str_starts_with($actual, $value)),
            'ends_with' => $expectedValues->contains(fn (string $value) => str_ends_with($actual, $value)),
            default => false,
        };
    }

    /** @param array<string, mixed> $condition */
    private function dateTimeMatches(array $condition, ResolutionContext $context): bool
    {
        $operator = (string) ($condition['operator'] ?? 'is');
        $now = $context->occurredAt;
        $timezone = $this->timezone($condition);

        return match ($operator) {
            'before' => ($value = $this->dateValue($condition['value'] ?? null, $timezone)) && $now->lessThan($value),
            'after' => ($value = $this->dateValue($condition['value'] ?? null, $timezone)) && $now->greaterThan($value),
            'between' => $this->betweenDates($now, $condition['value'] ?? null, $timezone),
            default => false,
        };
    }

    private function dateValue(mixed $value, string $timezone): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    private function betweenDates(CarbonImmutable $now, mixed $value, string $timezone): bool
    {
        [$from, $to] = $this->rangeValues($value);
        $from = $this->dateValue($from, $timezone);
        $to = $this->dateValue($to, $timezone);

        return $from && $to && $now->betweenIncluded($from, $to);
    }

    /** @param array<string, mixed> $condition */
    private function dayOfWeekMatches(array $condition, ResolutionContext $context): bool
    {
        $day = mb_strtolower($context->occurredAt->setTimezone($this->timezone($condition))->format('l'));

        return $this->scalarMatches($day, (string) ($condition['operator'] ?? 'is'), $condition['value'] ?? null);
    }

    /** @param array<string, mixed> $condition */
    private function timeOfDayMatches(array $condition, ResolutionContext $context): bool
    {
        $operator = (string) ($condition['operator'] ?? 'between');
        $minutes = $this->minutes($context->occurredAt->setTimezone($this->timezone($condition))->format('H:i'));

        if ($minutes === null) {
            return false;
        }

        if ($operator === 'before' || $operator === 'after') {
            $value = $this->minutes(is_string($condition['value'] ?? null) ? $condition['value'] : null);

            return $value !== null && ($operator === 'before' ? $minutes < $value : $minutes > $value);
        }

        if ($operator !== 'between') {
            return false;
        }

        [$from, $to] = $this->rangeValues($condition['value'] ?? null);
        $from = $this->minutes($from);
        $to = $this->minutes($to);

        if ($from === null || $to === null) {
            return false;
        }

        return $from <= $to
            ? $minutes >= $from && $minutes <= $to
            : $minutes >= $from || $minutes <= $to;
    }

    /** @param array<string, mixed> $condition */
    private function timezone(array $condition): string
    {
        $timezone = (string) ($condition['timezone'] ?? 'UTC');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    /** @return array{mixed, mixed} */
    private function rangeValues(mixed $value): array
    {
        if (is_array($value)) {
            return [$value['from'] ?? $value[0] ?? null, $value['to'] ?? $value[1] ?? null];
        }

        return [null, null];
    }

    private function minutes(?string $value): ?int
    {
        if (! is_string($value) || ! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    private function variantFor(RoutingRule $rule, ResolutionContext $context): ?RoutingVariant
    {
        /** @var Collection<int, RoutingVariant> $variants */
        $variants = $rule->variants
            ->filter(fn (RoutingVariant $variant) => $variant->is_enabled && $variant->weight > 0)
            ->values();

        if ($variants->isEmpty()) {
            return null;
        }

        $total = $variants->sum('weight');
        $slot = hexdec(substr($context->visitorHash, 0, 8)) % $total;
        $cursor = 0;

        foreach ($variants as $variant) {
            $cursor += $variant->weight;

            if ($slot < $cursor) {
                return $variant;
            }
        }

        return $variants->last();
    }

    /** @param array<string, string> $options */
    private function options(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    /** @param list<string> $values */
    private function plainOptions(array $values): array
    {
        return collect($values)
            ->map(fn (string $value) => ['value' => $value, 'label' => $value])
            ->values()
            ->all();
    }
}

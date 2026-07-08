<?php

namespace App\Services\Routing;

use App\Models\RoutingRule;
use Illuminate\Validation\Rule;

class SmartRoutingSchema
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

    public const CONDITION_TYPES = [
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

    public const OPERATORS = [
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

    public function contextKey(string $conditionType): string
    {
        return match ($conditionType) {
            'operating_system' => 'os',
            default => $conditionType,
        };
    }

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

    /** @return array<string, mixed> */
    public function validationRules(): array
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

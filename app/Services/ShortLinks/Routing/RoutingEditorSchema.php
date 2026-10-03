<?php

namespace App\Services\ShortLinks\Routing;

use App\Models\RoutingRule;

class RoutingEditorSchema
{
    public const SCALAR = 'scalar';

    public const TIME = 'time';

    private const CONDITION_TYPES = [
        'country' => ['label' => 'Country', 'operatorGroup' => self::SCALAR],
        'language' => ['label' => 'Language', 'operatorGroup' => self::SCALAR],
        'device_type' => ['label' => 'Device', 'operatorGroup' => self::SCALAR],
        'browser' => ['label' => 'Browser', 'operatorGroup' => self::SCALAR],
        'operating_system' => ['label' => 'OS', 'operatorGroup' => self::SCALAR],
        'referrer_host' => ['label' => 'Referrer host', 'operatorGroup' => self::SCALAR],
        'referrer_channel' => ['label' => 'Referrer channel', 'operatorGroup' => self::SCALAR],
        'utm_source' => ['label' => 'UTM source', 'operatorGroup' => self::SCALAR],
        'utm_medium' => ['label' => 'UTM medium', 'operatorGroup' => self::SCALAR],
        'utm_campaign' => ['label' => 'UTM campaign', 'operatorGroup' => self::SCALAR],
        'utm_term' => ['label' => 'UTM term', 'operatorGroup' => self::SCALAR],
        'utm_content' => ['label' => 'UTM content', 'operatorGroup' => self::SCALAR],
        'date_time' => ['label' => 'Date/time', 'operatorGroup' => self::TIME, 'zoned' => true, 'defaultOperator' => 'after', 'defaultValue' => ''],
        'day_of_week' => ['label' => 'Day', 'operatorGroup' => self::SCALAR, 'zoned' => true],
        'time_of_day' => [
            'label' => 'Time',
            'operatorGroup' => self::TIME,
            'zoned' => true,
            'defaultOperator' => 'between',
            'defaultValue' => ['from' => '09:00', 'to' => '18:00'],
        ],
    ];

    private const OPERATORS = [
        self::SCALAR => [
            'is' => 'is',
            'is_not' => 'is not',
            'contains' => 'contains',
            'does_not_contain' => 'does not contain',
            'starts_with' => 'starts with',
            'ends_with' => 'ends with',
            'is_empty' => 'is empty',
            'is_not_empty' => 'is not empty',
        ],
        self::TIME => [
            'before' => 'before',
            'after' => 'after',
            'between' => 'between',
        ],
    ];

    private const VALUE_OPTIONS = [
        'day_of_week' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'device_type' => ['mobile', 'desktop', 'tablet', 'bot'],
        'referrer_channel' => ['direct', 'search', 'social', 'video', 'email', 'messaging', 'ai', 'referral'],
    ];

    private const DEFAULTS = [
        'device_type' => 'mobile',
        'referrer_channel' => 'social',
        'country' => 'FR',
        'language' => 'fr',
    ];

    private const PRESETS = [
        ['kind' => 'country', 'label' => 'Country', 'description' => 'Send visitors to a destination by country.', 'conditionType' => 'country', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
        ['kind' => 'device', 'label' => 'Device', 'description' => 'Route mobile, desktop, tablet, or bot traffic.', 'conditionType' => 'device_type', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
        ['kind' => 'campaign', 'label' => 'Campaign', 'description' => 'Match UTM campaign parameters.', 'conditionType' => 'utm_campaign', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
        ['kind' => 'time', 'label' => 'Time', 'description' => 'Use a time window with an explicit timezone.', 'conditionType' => 'time_of_day', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
        ['kind' => 'split', 'label' => 'Split test', 'description' => 'Split traffic between weighted variants.', 'conditionType' => 'custom', 'ruleType' => RoutingRule::TYPE_SPLIT_TEST],
        ['kind' => 'custom', 'label' => 'Custom', 'description' => 'Start from a blank rule.', 'conditionType' => 'custom', 'ruleType' => RoutingRule::TYPE_CONDITIONAL],
    ];

    public static function conditionTypes(): array
    {
        return array_keys(self::CONDITION_TYPES);
    }

    public static function operators(): array
    {
        return [...array_keys(self::OPERATORS[self::SCALAR]), ...array_keys(self::OPERATORS[self::TIME])];
    }

    public function payload(): array
    {
        return [
            'conditionTypes' => collect(self::CONDITION_TYPES)
                ->map(fn (array $type, string $value) => ['value' => $value, ...$type])
                ->values()
                ->all(),
            'operators' => collect(self::OPERATORS)
                ->map(fn (array $operators) => $this->options($operators))
                ->all(),
            'valueOptions' => collect(self::VALUE_OPTIONS)
                ->map(fn (array $values) => $this->options(array_combine($values, $values)))
                ->all(),
            'defaults' => self::DEFAULTS,
            'presets' => self::PRESETS,
        ];
    }

    private function options(array $options): array
    {
        return collect($options)
            ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }
}

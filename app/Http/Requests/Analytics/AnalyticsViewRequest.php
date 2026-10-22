<?php

namespace App\Http\Requests\Analytics;

use App\Enums\AnalyticsMetric;
use App\Services\Analytics\AnalyticsFilters;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnalyticsViewRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:80'],
        ];

        if ($this->isMethod('PATCH')) {
            return $rules;
        }

        return $rules + [
            'filters' => ['required', 'array'],
            'filters.range' => ['required', Rule::in(AnalyticsFilters::RANGES)],
            'filters.from' => ['exclude_unless:filters.range,custom', 'required', 'date_format:Y-m-d'],
            'filters.to' => ['exclude_unless:filters.range,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:filters.from'],
            'filters.metric' => ['nullable', Rule::enum(AnalyticsMetric::class)],
            ...collect(AnalyticsFilters::TARGET_KEYS)->mapWithKeys(fn (string $key) => ["filters.{$key}" => ['nullable', 'integer', 'min:1']])->all(),
        ];
    }

    public function attributes(): array
    {
        return [
            'filters.from' => 'start date',
            'filters.to' => 'end date',
        ];
    }

    public function filters(): AnalyticsFilters
    {
        return AnalyticsFilters::fromQuery($this->validated('filters'));
    }
}

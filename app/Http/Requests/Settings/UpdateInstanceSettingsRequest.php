<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInstanceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('administer-instance');
    }

    public function rules(): array
    {
        $rules = [
            'registration_mode' => ['required', 'in:closed,invite_only,open'],
            'require_email_verification' => ['required', 'boolean'],
            'default_domain' => ['required', 'string', 'max:255'],
            'dns_target' => ['nullable', 'string', 'max:255'],
            'slug_length' => ['required', 'integer', 'min:4', 'max:32'],
            'analytics_retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
            'destination_monitoring' => ['sometimes', 'boolean'],
            'reserved_slugs' => ['nullable', 'string'],
            'reserved_prefixes' => ['nullable', 'string'],
            'public_error_branding' => ['sometimes', 'boolean'],
            'public_unavailable_title' => ['required', 'string', 'max:120'],
            'public_unavailable_message' => ['required', 'string', 'max:500'],
        ];

        if ($this->routeIs('api.*')) {
            foreach ($rules as $field => $rule) {
                $rules[$field] = ['sometimes', ...$rule];
            }
        }

        return $rules;
    }
}

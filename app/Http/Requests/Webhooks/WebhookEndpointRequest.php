<?php

namespace App\Http\Requests\Webhooks;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Enums\WebhookEventType;
use App\Services\Webhooks\UnsafeWebhookTarget;
use App\Services\Webhooks\WebhookTargets;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookEndpointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', app(CurrentWorkspace::class)->require());
    }

    public function rules(): array
    {
        return [
            'url' => ['bail', 'required', 'string', 'max:2048', 'url:https', $this->publicTarget(...)],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string', 'distinct', Rule::in(WebhookEventType::subscribableValues())],
            'is_enabled' => [$this->isMethod('PATCH') ? 'required' : 'sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'url.url' => 'Enter an HTTPS URL.',
            'events.required' => 'Select at least one event.',
            'events.min' => 'Select at least one event.',
        ];
    }

    public function events(): array
    {
        return $this->validated('events');
    }

    public function isEnabled(): bool
    {
        return $this->boolean('is_enabled');
    }

    private function publicTarget(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(WebhookTargets::class)->resolve((string) $value);
        } catch (UnsafeWebhookTarget $exception) {
            $fail($exception->getMessage());
        }
    }
}

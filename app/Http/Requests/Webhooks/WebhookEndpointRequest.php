<?php

namespace App\Http\Requests\Webhooks;

use App\Actions\Webhooks\WebhookEndpointMutation;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Enums\WebhookEventType;
use App\Models\WebhookEndpoint;
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
            'url' => array_filter([
                'bail',
                $this->isMethod('POST') ? $this->withinEndpointLimit(...) : null,
                'required',
                'string',
                'max:2048',
                'url:https',
                $this->publicTarget(...),
            ]),
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

    private function withinEndpointLimit(string $attribute, mixed $value, Closure $fail): void
    {
        if (WebhookEndpoint::limitReachedIn(app(CurrentWorkspace::class)->require())) {
            $fail(WebhookEndpointMutation::limitMessage());
        }
    }

    private function publicTarget(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(WebhookTargets::class)->assertAcceptable((string) $value);
        } catch (UnsafeWebhookTarget $exception) {
            $fail($exception->getMessage());
        }
    }
}

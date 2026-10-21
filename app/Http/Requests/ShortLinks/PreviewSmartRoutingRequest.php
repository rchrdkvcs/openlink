<?php

namespace App\Http\Requests\ShortLinks;

use App\Actions\ShortLinks\PreviewSmartRouting;
use App\Models\ShortLink;
use App\Services\ShortLinks\Routing\RoutingRulesValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewSmartRoutingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->previewsDraft() ? 'update' : 'view', $this->shortLink());
    }

    public function shortLink(): ShortLink
    {
        return $this->route('shortLink');
    }

    public function previewsDraft(): bool
    {
        return $this->input('source') === PreviewSmartRouting::SOURCE_DRAFT;
    }

    public function rules(): array
    {
        $text = ['nullable', 'string', 'max:250'];

        return [
            'source' => ['required', Rule::in([PreviewSmartRouting::SOURCE_SAVED, PreviewSmartRouting::SOURCE_DRAFT])],
            'destination_url' => ['exclude_unless:source,'.PreviewSmartRouting::SOURCE_DRAFT, 'required', 'url:http,https'],
            ...RoutingRulesValidator::inputRules(),
            'context' => ['required', 'array'],
            'context.country' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'context.language' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'context.device_type' => ['nullable', Rule::in(['mobile', 'desktop', 'tablet', 'bot', 'unknown'])],
            'context.browser' => $text,
            'context.operating_system' => $text,
            'context.referrer' => ['nullable', 'string', 'max:2048'],
            'context.utm_source' => $text,
            'context.utm_medium' => $text,
            'context.utm_campaign' => $text,
            'context.utm_term' => $text,
            'context.utm_content' => $text,
            'context.occurred_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'context.timezone' => ['required', 'timezone'],
            'context.seed' => ['nullable', 'string', 'max:100'],
        ];
    }
}

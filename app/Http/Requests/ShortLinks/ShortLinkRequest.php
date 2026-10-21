<?php

namespace App\Http\Requests\ShortLinks;

use App\Models\Workspace;
use App\Services\ShortLinks\Routing\RoutingRulesValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ShortLinkRequest extends FormRequest
{
    private const PREVIEW_TEXT_FIELDS = ['preview_title', 'preview_description'];

    private const MARKUP = '/<\s*[a-z!\/?]|[\x00-\x1F\x7F]/i';

    abstract public function workspace(): ?Workspace;

    abstract protected function creating(): bool;

    public function rules(): array
    {
        $address = $this->creating() ? ['nullable'] : ['sometimes', 'required'];

        return [
            ...self::configurationRules(),
            'domain_id' => [...$address, 'integer'],
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $this->workspace()?->id)],
            'slug' => [...$address, 'string', 'max:512'],
            'is_enabled' => [$this->creating() ? 'nullable' : 'required', 'boolean'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
            'expected_revision' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public static function configurationRules(): array
    {
        return [
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => ['required', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'tags' => ['nullable', 'string', 'max:500'],
            'preview_title' => ['sometimes', 'nullable', 'string', 'max:120', 'not_regex:'.self::MARKUP],
            'preview_description' => ['sometimes', 'nullable', 'string', 'max:300', 'not_regex:'.self::MARKUP],
            'preview_image' => [
                'sometimes',
                'nullable',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:5120',
                'dimensions:min_width=200,min_height=200,max_width=4096,max_height=4096',
            ],
            'remove_preview_image' => ['sometimes', 'boolean'],
            'conversion_goal' => ['sometimes', 'nullable', 'string', 'max:80'],
            ...RoutingRulesValidator::inputRules(),
        ];
    }

    public function messages(): array
    {
        return collect(self::PREVIEW_TEXT_FIELDS)
            ->mapWithKeys(fn (string $field) => [$field.'.not_regex' => __('openlink.validation.preview_text_markup')])
            ->all();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(self::PREVIEW_TEXT_FIELDS)
            ->filter(fn (string $field) => is_string($this->input($field)))
            ->mapWithKeys(fn (string $field) => [$field => trim((string) preg_replace('/[ \t\r\n]+/', ' ', $this->input($field)))])
            ->all());
    }
}

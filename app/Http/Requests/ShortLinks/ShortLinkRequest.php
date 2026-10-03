<?php

namespace App\Http\Requests\ShortLinks;

use App\Models\Workspace;
use App\Services\ShortLinks\Routing\RoutingRulesValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ShortLinkRequest extends FormRequest
{
    abstract public function workspace(): ?Workspace;

    abstract protected function creating(): bool;

    public function rules(): array
    {
        $address = $this->creating() ? ['nullable'] : ['sometimes', 'required'];

        return [
            'domain_id' => [...$address, 'integer'],
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $this->workspace()?->id)],
            'slug' => [...$address, 'string', 'max:512'],
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => [$this->creating() ? 'nullable' : 'required', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
            ...RoutingRulesValidator::inputRules(),
        ];
    }
}

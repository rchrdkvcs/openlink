<?php

namespace App\Http\Requests\ShortLinks;

use App\Models\ShortLink;
use App\Services\ShortLinks\Routing\RoutingRulesValidator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShortLinkRoutingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->shortLink());
    }

    public function shortLink(): ShortLink
    {
        return $this->route('shortLink');
    }

    public function rules(): array
    {
        return [
            'destination_url' => ['required', 'url:http,https'],
            ...RoutingRulesValidator::inputRules(),
        ];
    }
}

<?php

namespace App\Http\Requests\ShortLinks;

use App\Models\ShortLink;
use App\Models\Workspace;

class UpdateShortLinkRequest extends ShortLinkRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        if ($this->routeIs('api.*')) {
            foreach (['destination_url', 'is_enabled'] as $field) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->shortLink());
    }

    public function shortLink(): ShortLink
    {
        return $this->route('shortLink');
    }

    public function workspace(): ?Workspace
    {
        return $this->shortLink()->workspace;
    }

    protected function creating(): bool
    {
        return false;
    }
}

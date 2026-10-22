<?php

namespace App\Http\Requests\ShortLinks;

use Illuminate\Foundation\Http\FormRequest;

class UnarchiveShortLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('shortLink'));
    }

    public function rules(): array
    {
        return [
            'expected_revision' => ['required', 'integer', 'min:0'],
        ];
    }
}

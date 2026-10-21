<?php

namespace App\Http\Requests\LinkPages;

use Illuminate\Foundation\Http\FormRequest;

class StoreLinkPageRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'path' => ['required', 'string', 'max:64'],
        ];
    }
}

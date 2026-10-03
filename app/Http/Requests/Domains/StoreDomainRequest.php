<?php

namespace App\Http\Requests\Domains;

use App\Models\Domain;
use Illuminate\Foundation\Http\FormRequest;

class StoreDomainRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255', 'unique:domains,hostname'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('hostname'))) {
            $this->merge(['hostname' => Domain::normalizeHostname($this->input('hostname'))]);
        }
    }
}

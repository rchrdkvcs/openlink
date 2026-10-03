<?php

namespace App\Http\Requests\QrCodes;

use App\Actions\QrCodes\QrCodeAppearance;
use App\Actions\QrCodes\QrCodeTargets;
use Illuminate\Foundation\Http\FormRequest;

class StoreQrCodeRequest extends FormRequest
{
    protected bool $nameRequired = true;

    public function rules(): array
    {
        return [
            'name' => [$this->nameRequired ? 'required' : 'sometimes', 'string', 'max:120'],
            ...QrCodeTargets::rules(),
            ...QrCodeAppearance::rules(),
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}

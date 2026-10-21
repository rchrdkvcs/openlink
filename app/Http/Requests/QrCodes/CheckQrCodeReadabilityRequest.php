<?php

namespace App\Http\Requests\QrCodes;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class CheckQrCodeReadabilityRequest extends PreviewQrCodeRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'print_width_mm' => ['nullable', 'numeric', 'min:5', 'max:5000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $validator->errors()->first(),
            'errors' => $validator->errors(),
        ], 422));
    }
}

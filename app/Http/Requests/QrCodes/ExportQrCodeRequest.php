<?php

namespace App\Http\Requests\QrCodes;

use App\Actions\QrCodes\QrCodeAppearance;
use Illuminate\Foundation\Http\FormRequest;

class ExportQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('qrCode')) ?? false;
    }

    public function rules(): array
    {
        return ['size' => QrCodeAppearance::rules()['size']];
    }

    public function size(): ?int
    {
        $size = $this->validated('size');

        return $size === null ? null : (int) $size;
    }
}

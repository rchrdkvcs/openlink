<?php

namespace App\Http\Requests\QrCodes;

use App\Actions\QrCodes\QrCodeAppearance;
use App\Actions\QrCodes\QrCodeTargets;
use App\Models\QrCode;
use Illuminate\Foundation\Http\FormRequest;

class PreviewQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->qrCode()) ?? false;
    }

    public function rules(): array
    {
        return [
            ...QrCodeAppearance::rules(),
            ...($this->qrCode()->hasDirectPayload() ? QrCodeTargets::payloadRules() : []),
        ];
    }

    public function qrCode(): QrCode
    {
        return $this->route('qrCode');
    }
}

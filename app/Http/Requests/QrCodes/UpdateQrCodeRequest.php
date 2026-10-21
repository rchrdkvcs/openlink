<?php

namespace App\Http\Requests\QrCodes;

use App\Actions\QrCodes\QrCodeTemplates;

class UpdateQrCodeRequest extends StoreQrCodeRequest
{
    protected bool $nameRequired = false;

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'logo_template_id' => QrCodeTemplates::logoRules($this->route('qrCode')),
        ];
    }
}

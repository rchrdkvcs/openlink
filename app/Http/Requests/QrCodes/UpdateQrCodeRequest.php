<?php

namespace App\Http\Requests\QrCodes;

class UpdateQrCodeRequest extends StoreQrCodeRequest
{
    protected bool $nameRequired = false;
}

<?php

namespace App\Services\QrCodes;

use App\Models\QrCode;
use App\Services\QrCodes\Payloads\PayloadType;
use App\Services\QrCodes\Payloads\PayloadTypes;
use Illuminate\Support\Facades\Validator;

class QrCodeContent
{
    public static function types(): array
    {
        return collect(PayloadTypes::all())->map(fn (PayloadType $type) => $type->descriptor()['label'])->all();
    }

    public static function descriptors(): array
    {
        return collect(PayloadTypes::all())->map(fn (PayloadType $type) => $type->descriptor())->all();
    }

    public function normalize(string $type, array $payload): string
    {
        $payloadType = PayloadTypes::get($type);

        Validator::make(['payload' => $payload], $payloadType->rules())->validate();

        return $payloadType->encode($payload);
    }

    public function shouldRedirect(QrCode $qrCode): bool
    {
        $payloadType = PayloadTypes::all()[$qrCode->payload_type] ?? null;

        return $payloadType !== null && $payloadType->redirects((string) $qrCode->content);
    }
}

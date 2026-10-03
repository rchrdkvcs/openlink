<?php

namespace App\Services\QrCodes\Payloads;

class PayloadTypes
{
    private const TYPES = [
        UrlPayload::class,
        TextPayload::class,
        EmailPayload::class,
        PhonePayload::class,
        SmsPayload::class,
        WifiPayload::class,
        VcardPayload::class,
        EventPayload::class,
        LocationPayload::class,
        RawPayload::class,
    ];

    public static function all(): array
    {
        return collect(self::TYPES)
            ->map(fn (string $class): PayloadType => new $class)
            ->keyBy(fn (PayloadType $type): string => $type->key())
            ->all();
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $key): PayloadType
    {
        $types = self::all();

        return $types[$key] ?? $types['raw'];
    }
}

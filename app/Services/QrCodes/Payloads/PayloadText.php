<?php

namespace App\Services\QrCodes\Payloads;

class PayloadText
{
    public static function phone(string $phone): string
    {
        return preg_replace('/[^0-9+*#;,]/', '', $phone) ?: trim($phone);
    }

    public static function wifi(string $value): string
    {
        return str_replace(['\\', ';', ',', ':'], ['\\\\', '\;', '\,', '\:'], $value);
    }

    public static function vcard(string $value): string
    {
        return str_replace(['\\', "\n", "\r", ';', ','], ['\\\\', '\n', '', '\;', '\,'], $value);
    }

    public static function optionalLines(array $payload, array $labels): array
    {
        $lines = [];

        foreach ($labels as $key => $label) {
            if (filled($payload[$key] ?? null)) {
                $lines[] = $label.':'.self::vcard((string) $payload[$key]);
            }
        }

        return $lines;
    }
}

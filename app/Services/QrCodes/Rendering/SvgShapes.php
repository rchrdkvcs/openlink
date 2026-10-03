<?php

namespace App\Services\QrCodes\Rendering;

class SvgShapes
{
    public static function rect(float $x, float $y, float $w, float $h): string
    {
        return sprintf('M%s %sh%sv%sh%sz', self::n($x), self::n($y), self::n($w), self::n($h), self::n(-$w));
    }

    public static function roundedRect(float $x, float $y, float $w, float $h, float $r): string
    {
        $r = min($r, $w / 2, $h / 2);
        $arc = [self::n($r), self::n($r), self::n($r), self::n($r)];

        return sprintf(
            'M%s %sh%sa%s %s 0 0 1 %s %sv%sa%s %s 0 0 1 -%s %sh-%sa%s %s 0 0 1 -%s -%sv-%sa%s %s 0 0 1 %s -%sz',
            self::n($x + $r), self::n($y),
            self::n($w - 2 * $r),
            ...$arc,
            ...[self::n($h - 2 * $r)],
            ...$arc,
            ...[self::n($w - 2 * $r)],
            ...$arc,
            ...[self::n($h - 2 * $r)],
            ...$arc,
        );
    }

    public static function circle(float $cx, float $cy, float $r): string
    {
        return sprintf(
            'M%s %sa%s %s 0 1 0 %s 0a%s %s 0 1 0 -%s 0z',
            self::n($cx - $r), self::n($cy),
            self::n($r), self::n($r), self::n(2 * $r),
            self::n($r), self::n($r), self::n(2 * $r),
        );
    }

    public static function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

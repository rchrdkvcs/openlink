<?php

namespace App\Enums;

use App\Models\QrCode;

enum AnalyticsMetric: string
{
    case Visit = 'visit';
    case Scan = 'scan';

    public static function forEntry(?QrCode $qrCode): self
    {
        return $qrCode ? self::Scan : self::Visit;
    }
}

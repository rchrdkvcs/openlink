<?php

namespace App\Enums;

enum CheckState: string
{
    case Healthy = 'healthy';
    case Stale = 'stale';
    case Missing = 'missing';
    case Unavailable = 'unavailable';
    case Disabled = 'disabled';
    case NotConfigured = 'not_configured';
}

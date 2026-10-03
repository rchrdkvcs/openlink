<?php

namespace App\Enums;

enum LinkStatus: string
{
    case Active = 'active';
    case Scheduled = 'scheduled';
    case Expired = 'expired';
    case Disabled = 'disabled';
    case Archived = 'archived';
}

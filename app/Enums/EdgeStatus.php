<?php

namespace App\Enums;

enum EdgeStatus: string
{
    case Waiting = 'waiting';
    case Issuing = 'issuing';
    case Connected = 'connected';
}

<?php

namespace App\Actions\Resolution;

enum ResolutionView
{
    case Redirect;
    case PasswordForm;
    case Scheduled;
    case QrPayload;
    case Unavailable;
}

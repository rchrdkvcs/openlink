<?php

namespace App\Enums;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

enum MutationChannel: string
{
    case Dashboard = 'dashboard';
    case Api = 'api';
    case System = 'system';

    public static function of(?Request $request): self
    {
        $route = $request?->route();

        return match (true) {
            $route === null => self::System,
            Str::startsWith((string) $route->getName(), 'api.') => self::Api,
            default => self::Dashboard,
        };
    }
}

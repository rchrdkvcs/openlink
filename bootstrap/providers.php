<?php

use App\Providers\AppServiceProvider;
use Laravel\Breeze\BreezeServiceProvider;
use Laravel\Pail\PailServiceProvider;
use Laravel\Pao\Laravel\ServiceProvider;
use NunoMaduro\Collision\Adapters\Laravel\CollisionServiceProvider;

$providers = [
    AppServiceProvider::class,
];

foreach ([
    BreezeServiceProvider::class,
    PailServiceProvider::class,
    ServiceProvider::class,
    CollisionServiceProvider::class,
] as $provider) {
    if (class_exists($provider)) {
        $providers[] = $provider;
    }
}

return $providers;

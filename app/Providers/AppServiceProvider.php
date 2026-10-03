<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Dns\DnsLookup;
use App\Services\Dns\SystemDnsLookup;
use App\Services\QrCodes\QrCodeLogoCleanup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Discord\Provider as DiscordProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DnsLookup::class, SystemDnsLookup::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define('administer-instance', fn (User $user): bool => (bool) $user->is_instance_admin);

        RateLimiter::for('public-resolution', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', DiscordProvider::class);
        });

        Event::subscribe(QrCodeLogoCleanup::class);
    }
}

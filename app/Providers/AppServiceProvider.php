<?php

namespace App\Providers;

use App\Jobs\CheckDestination;
use App\Models\User;
use App\Services\Diagnostics\Heartbeats;
use App\Services\Dns\DnsLookup;
use App\Services\Dns\SystemDnsLookup;
use App\Services\LinkPreviews\LinkPreviewImageCleanup;
use App\Services\QrCodes\QrCodeLogoCleanup;
use App\Services\ShortLinks\History\UrlRedaction;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
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
        $this->app->bind(UrlRedaction::class, fn ($app) => new UrlRedaction((string) $app['config']->get('app.key')));
        $this->app->singleton(Heartbeats::class);
    }

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::define('administer-instance', fn (User $user): bool => (bool) $user->is_instance_admin);

        RateLimiter::for('public-resolution', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('destination-checks', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for(CheckDestination::HOST_RATE_LIMITER, function (CheckDestination $job) {
            return Limit::perMinute(6)->by($job->host);
        });

        RateLimiter::for('qr-readability', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('instance-diagnostics', function (Request $request) {
            return Limit::perMinute(6)->by((string) $request->user()?->id);
        });

        RateLimiter::for('instance-diagnostics-mail', function (Request $request) {
            return Limit::perMinutes(10, 3)->by((string) $request->user()?->id);
        });

        Event::listen([Looping::class, JobProcessing::class, JobProcessed::class], fn (Looping|JobProcessing|JobProcessed $event) => $this->app->make(Heartbeats::class)->workerActive($event->connectionName));

        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('discord', DiscordProvider::class);
        });

        Event::subscribe(QrCodeLogoCleanup::class);
        Event::subscribe(LinkPreviewImageCleanup::class);
    }
}

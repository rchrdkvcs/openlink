<?php

namespace App\Http\Middleware;

use App\Services\InstanceSettings;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedWhenRequired
{
    public function __construct(
        private readonly InstanceSettings $settings,
        private readonly EnsureEmailIsVerified $verification,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->get('require_email_verification')) {
            return $next($request);
        }

        return $this->verification->handle($request, $next);
    }
}

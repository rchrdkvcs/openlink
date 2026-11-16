<?php

namespace App\Services\ErrorReporting;

use App\Services\ApplicationHost;
use Illuminate\Http\Request;

class BrowserErrorReporting
{
    public function __construct(private readonly ApplicationHost $applicationHost) {}

    public function forRequest(Request $request): ?array
    {
        $dsn = config('services.sentry.browser_dsn');

        if (! is_string($dsn) || $dsn === '') {
            return null;
        }

        if (! $this->applicationHost->isApplicationHost($request->getHost()) || $request->routeIs(...TracesSampler::PUBLIC_ROUTES)) {
            return null;
        }

        return [
            'dsn' => $dsn,
            'environment' => config('sentry.environment') ?: app()->environment(),
            'release' => config('sentry.release') ?: null,
            'tracesSampleRate' => max(0.0, min(1.0, (float) config('services.sentry.browser_traces_sample_rate'))),
        ];
    }
}

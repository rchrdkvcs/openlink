<?php

namespace App\Services\ErrorReporting;

use App\Services\ApplicationHost;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Sentry\Tracing\SamplingContext;
use Throwable;

class TracesSampler
{
    public const PUBLIC_ROUTES = ['public.*', 'app-associations.*'];

    public function __construct(
        private readonly ApplicationHost $applicationHost,
        private readonly Router $router,
    ) {}

    public static function sample(SamplingContext $context): float
    {
        $sampler = app(self::class);

        if ($context->getTransactionContext()?->getOp() !== 'http.server') {
            return $sampler->configuredRate();
        }

        return $sampler->rateForRequest(app('request'));
    }

    public function rateForRequest(Request $request): float
    {
        if (! $this->applicationHost->isApplicationHost($request->getHost())) {
            return 0.0;
        }

        try {
            $route = $this->router->getRoutes()->match($request);
        } catch (Throwable) {
            return 0.0;
        }

        return $route->named(...self::PUBLIC_ROUTES) ? 0.0 : $this->configuredRate();
    }

    public function configuredRate(): float
    {
        return max(0.0, min(1.0, (float) config('sentry.traces_sample_rate')));
    }
}

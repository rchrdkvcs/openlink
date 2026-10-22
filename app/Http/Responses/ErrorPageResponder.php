<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ErrorPageResponder
{
    private const STATUSES = [403, 404, 419, 429, 500, 503];

    private const FORWARDED_HEADERS = [
        'Retry-After',
        'Refresh',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ];

    public function toResponse(Request $request, Response $response): Response
    {
        if (! $this->shouldRender($request, $response)) {
            return $response;
        }

        try {
            return Inertia::render('Error', ['status' => $response->getStatusCode()])
                ->toResponse($request)
                ->setStatusCode($response->getStatusCode())
                ->withHeaders($this->forwardedHeaders($response));
        } catch (Throwable) {
            return $response;
        }
    }

    private function shouldRender(Request $request, Response $response): bool
    {
        return ! config('app.debug')
            && in_array($response->getStatusCode(), self::STATUSES, true)
            && ! $response instanceof JsonResponse
            && ! $response instanceof RedirectResponse
            && ! $request->expectsJson()
            && ! $request->is('api/*');
    }

    private function forwardedHeaders(Response $response): array
    {
        $headers = [];

        foreach (self::FORWARDED_HEADERS as $name) {
            $value = $response->headers->get($name);

            if ($value !== null) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}

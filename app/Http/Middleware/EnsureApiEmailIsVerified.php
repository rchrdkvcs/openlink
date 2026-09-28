<?php

namespace App\Http\Middleware;

use App\Services\InstanceSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(InstanceSettings::class)->get('require_email_verification') && $request->user() && ! $request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Verify your email address before using the API.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureHasWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->workspaceMemberships()->exists()) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Actions\Workspaces\WorkspaceShell;
use App\Services\ErrorReporting\BrowserErrorReporting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    ...$user->only(['id', 'name', 'email', 'email_verified_at', 'is_instance_admin']),
                    'profile_avatar_url' => $user->profileAvatarUrl(),
                ] : null,
            ],
            'errorReporting' => app(BrowserErrorReporting::class)->forRequest($request),
            ...app(WorkspaceShell::class, ['request' => $request])->props(),
        ];
    }
}

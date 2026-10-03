<?php

namespace App\Http\Middleware;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\InviteLink;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\WorkspaceMember;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeBindingsToCurrentWorkspace
{
    private const OUTSIDE_STATUS = [
        Domain::class => 403,
        Folder::class => 403,
        InviteLink::class => 403,
        QrCode::class => 403,
        ShortLink::class => 403,
        WorkspaceMember::class => 404,
    ];

    public function __construct(private readonly CurrentWorkspace $currentWorkspace) {}

    public function handle(Request $request, Closure $next): Response
    {
        $records = collect($request->route()?->parameters() ?? [])->filter(fn ($value) => $this->isWorkspaceOwned($value));

        if ($records->isNotEmpty()) {
            $workspace = $this->currentWorkspace->get();
            abort_unless($workspace, 403);

            $records->each(fn (Model $record) => abort_unless(
                (int) $record->getAttribute('workspace_id') === $workspace->getKey(),
                self::OUTSIDE_STATUS[$record::class],
            ));
        }

        return $next($request);
    }

    private function isWorkspaceOwned(mixed $value): bool
    {
        return $value instanceof Model && array_key_exists($value::class, self::OUTSIDE_STATUS);
    }
}

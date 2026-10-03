<?php

namespace App\Actions\Workspaces;

use App\Models\Workspace;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Http\Request;

class CurrentWorkspace
{
    private const SESSION_KEY = 'workspace_id';

    private const RESOLVED = 'openlink.current_workspace';

    public function __construct(
        private readonly Request $request,
        private readonly Gate $gate,
    ) {}

    public function get(): ?Workspace
    {
        if (! $this->request->attributes->has(self::RESOLVED)) {
            $this->request->attributes->set(self::RESOLVED, $this->resolve());
        }

        return $this->request->attributes->get(self::RESOLVED);
    }

    public function require(string $ability = 'view'): Workspace
    {
        $workspace = $this->get();
        abort_unless($workspace, 403);

        $this->gate->forUser($this->request->user())->authorize($ability, $workspace);

        return $workspace;
    }

    public function is(Workspace $workspace): bool
    {
        return $this->get()?->is($workspace) ?? false;
    }

    public function select(Workspace $workspace): void
    {
        if ($this->request->hasSession()) {
            $this->request->session()->put(self::SESSION_KEY, $workspace->getKey());
        }

        $this->request->attributes->set(self::RESOLVED, $workspace);
    }

    public function forget(): void
    {
        if ($this->request->hasSession()) {
            $this->request->session()->forget(self::SESSION_KEY);
        }

        $this->request->attributes->remove(self::RESOLVED);
    }

    private function resolve(): ?Workspace
    {
        $user = $this->request->user();

        if (! $user) {
            return null;
        }

        $workspaces = Workspace::query()
            ->whereHas('members', fn ($members) => $members->where('user_id', $user->id));

        $headerId = $this->request->headers->get('X-Workspace-Id');

        if ($headerId !== null && $headerId !== '') {
            return (clone $workspaces)->whereKey((int) $headerId)->first();
        }

        $sessionId = $this->request->hasSession() ? $this->request->session()->get(self::SESSION_KEY) : null;

        return ($sessionId ? (clone $workspaces)->whereKey($sessionId)->first() : null)
            ?? $workspaces->oldest('workspaces.id')->first();
    }
}

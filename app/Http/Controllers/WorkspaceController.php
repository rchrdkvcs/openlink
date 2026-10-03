<?php

namespace App\Http\Controllers;

use App\Actions\Domains\DomainPayload;
use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Domain;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function store(StoreWorkspaceRequest $request, CreateWorkspace $workspaces, CurrentWorkspace $current): RedirectResponse
    {
        $data = $request->validated();

        $current->select($workspaces->handle($request->user(), $data['name'], $data['icon'] ?? null, $data['color'] ?? null));

        return back();
    }

    public function switch(Request $request, Workspace $workspace, CurrentWorkspace $current): RedirectResponse
    {
        $data = $request->validate([
            'destination' => ['nullable', 'string', Rule::in([
                'dashboard',
                'links.index',
                'qr-codes.index',
                'analytics.index',
                'domains.index',
                'members.index',
                'settings.index',
                'settings.workspace',
            ])],
        ]);

        Gate::authorize('view', $workspace);
        $current->select($workspace);

        return redirect()->route($data['destination'] ?? 'dashboard');
    }

    public function settings(Request $request, CurrentWorkspace $current, DomainPayload $domains): Response|RedirectResponse
    {
        $user = $request->user();
        $workspace = $current->require();

        if ($user->cannot('manage', $workspace)) {
            return redirect()->route('profile.edit');
        }

        return Inertia::render('Settings/Workspace', [
            'domains' => $domains->forWorkspace($workspace)
                ->filter(fn (array $domain) => $domain['is_default'] || $domain['status'] === 'active')
                ->values(),
            'canDelete' => $this->canDelete($user, $workspace),
        ]);
    }

    public function manage(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('manage', $workspace);

        $user = $request->user();
        $domains = $workspace->domains()->orderBy('hostname')->get(['id', 'hostname']);
        $defaultDomain = Domain::query()->where('is_default', true)->first(['id', 'hostname']);

        if ($defaultDomain) {
            $domains->prepend($defaultDomain);
        }

        return response()->json([
            'id' => $workspace->id,
            'name' => $workspace->name,
            'icon' => $workspace->icon,
            'color' => $workspace->color,
            'preferred_domain_id' => $workspace->preferred_domain_id,
            'role' => $user->roleIn($workspace)?->value,
            'can_delete' => $this->canDelete($user, $workspace),
            'domains' => $domains->unique('id')->values(),
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace, UpdateWorkspace $workspaces): RedirectResponse
    {
        $data = $request->validated();

        $workspaces->handle($request->user(), $workspace, $data['name'], $data['preferred_domain_id'] ?? null, $data['icon'] ?? null, $data['color'] ?? null);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, CurrentWorkspace $current, DeleteWorkspace $workspaces): RedirectResponse
    {
        $wasCurrent = $current->is($workspace);

        $nextWorkspace = $workspaces->handle($request->user(), $workspace);

        if ($wasCurrent) {
            $current->select($nextWorkspace);
        }

        return redirect()->route('dashboard');
    }

    private function canDelete(User $user, Workspace $workspace): bool
    {
        return $user->can('delete', $workspace) && $user->workspaces()->count() > 1;
    }
}

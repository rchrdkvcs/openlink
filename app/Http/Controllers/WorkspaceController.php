<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function store(Request $request, CreateWorkspace $workspaces): RedirectResponse
    {
        $data = $request->validate($this->rules());

        $workspace = $workspaces->handle($request->user(), $data['name'], $data['icon'] ?? null, $data['color'] ?? null);

        $request->session()->put('workspace_id', $workspace->id);

        return back();
    }

    public function switch(Request $request, Workspace $workspace, WorkspaceAccess $access): RedirectResponse
    {
        $access->selectCurrent($request, $workspace);

        return back();
    }

    public function manage(Request $request, Workspace $workspace, WorkspaceAccess $access): JsonResponse
    {
        $user = $request->user();
        $role = $access->role($user, $workspace);

        abort_unless($access->canManageWorkspace($user, $workspace), 403);

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
            'role' => $role,
            'can_delete' => $role === WorkspaceMember::ROLE_OWNER && $user->workspaces()->count() > 1,
            'domains' => $domains->unique('id')->values(),
        ]);
    }

    public function update(Request $request, Workspace $workspace, WorkspaceAccess $access, UpdateWorkspace $workspaces): RedirectResponse
    {
        abort_unless($access->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            ...$this->rules(),
            'preferred_domain_id' => ['nullable', 'integer'],
        ]);

        $workspaces->handle($workspace, $data['name'], $data['preferred_domain_id'] ?? null, $data['icon'] ?? null, $data['color'] ?? null);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceAccess $access, DeleteWorkspace $workspaces): RedirectResponse
    {
        abort_unless($access->role($request->user(), $workspace) === WorkspaceMember::ROLE_OWNER, 403);

        $wasCurrent = (int) $request->session()->get('workspace_id') === $workspace->id;

        $nextWorkspace = $workspaces->handle($request->user(), $workspace);

        if ($wasCurrent) {
            $request->session()->put('workspace_id', $nextWorkspace->id);
        }

        return redirect()->route('workspaces.index');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', Rule::in(Workspace::ICONS)],
            'color' => ['nullable', 'string', Rule::in(Workspace::COLORS)],
        ];
    }
}

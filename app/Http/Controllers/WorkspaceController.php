<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function store(Request $request, CreateWorkspace $workspaces): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $workspace = $workspaces->handle($request->user(), $data['name']);

        $request->session()->put('workspace_id', $workspace->id);

        return back();
    }

    public function switch(Request $request, Workspace $workspace, WorkspaceAccess $access): RedirectResponse
    {
        $access->selectCurrent($request, $workspace);

        return back();
    }

    public function update(Request $request, WorkspaceAccess $access, UpdateWorkspace $workspaces): RedirectResponse
    {
        $workspace = $access->requireManagedWorkspace($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'preferred_domain_id' => ['nullable', 'integer'],
        ]);

        $workspaces->handle($workspace, $data['name'], $data['preferred_domain_id'] ?? null);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceAccess $access, DeleteWorkspace $workspaces): RedirectResponse
    {
        abort_unless($access->role($request->user(), $workspace) === WorkspaceMember::ROLE_OWNER, 403);

        $nextWorkspace = $workspaces->handle($request->user(), $workspace);

        $request->session()->put('workspace_id', $nextWorkspace->id);

        return redirect()->route('workspaces.index');
    }
}

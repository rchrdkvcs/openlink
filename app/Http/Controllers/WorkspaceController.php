<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function store(Request $request, WorkspaceManager $workspaces): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $workspace = $workspaces->create($request->user(), $data['name']);

        $request->session()->put('workspace_id', $workspace->id);

        return back();
    }

    public function switch(Request $request, Workspace $workspace, WorkspaceContext $context): RedirectResponse
    {
        $context->setCurrent($request, $workspace);

        return back();
    }

    public function update(Request $request, WorkspaceContext $context, WorkspaceManager $workspaces): RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'preferred_domain_id' => ['nullable', 'integer'],
        ]);

        $workspaces->update($workspace, $data['name'], $data['preferred_domain_id'] ?? null);

        return back();
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceContext $context, WorkspaceManager $workspaces): RedirectResponse
    {
        abort_unless($context->role($request->user(), $workspace) === WorkspaceMember::ROLE_OWNER, 403);

        $nextWorkspace = $workspaces->destroy($request->user(), $workspace);

        $request->session()->put('workspace_id', $nextWorkspace->id);

        return redirect()->route('workspaces.index');
    }
}

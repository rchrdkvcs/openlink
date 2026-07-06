<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->workspaces()
                ->orderBy('workspaces.created_at')
                ->orderBy('workspaces.id')
                ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.preferred_domain_id'])
                ->map(fn ($workspace) => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'preferred_domain_id' => $workspace->preferred_domain_id,
                    'role' => $workspace->pivot->role,
                ]),
        ]);
    }

    public function current(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'preferred_domain_id' => $workspace->preferred_domain_id,
                'role' => $access->role($user, $workspace),
                'can_manage' => $access->canManageWorkspace($user, $workspace),
                'can_edit' => $access->canEditWorkspace($user, $workspace),
            ],
        ]);
    }

    public function store(Request $request, CreateWorkspace $workspaces): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $workspace = $workspaces->handle($request->user(), $data['name']);

        return response()->json([
            'data' => $workspace->only(['id', 'name', 'slug', 'preferred_domain_id']),
        ], 201);
    }

    public function update(Request $request, WorkspaceAccess $access, UpdateWorkspace $workspaces): JsonResponse
    {
        $workspace = $access->requireManagedWorkspace($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'preferred_domain_id' => ['nullable', 'integer'],
        ]);

        $workspace = $workspaces->handle($workspace, $data['name'], $data['preferred_domain_id'] ?? null);

        return response()->json([
            'data' => $workspace->only(['id', 'name', 'slug', 'preferred_domain_id']),
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceAccess $access, DeleteWorkspace $workspaces): JsonResponse
    {
        abort_unless($access->role($request->user(), $workspace) === WorkspaceMember::ROLE_OWNER, 403);

        $nextWorkspace = $workspaces->handle($request->user(), $workspace);

        return response()->json([
            'message' => 'Workspace deleted.',
            'next_workspace_id' => $nextWorkspace->id,
        ]);
    }
}

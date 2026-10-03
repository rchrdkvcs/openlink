<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\StoreWorkspaceRequest;
use App\Http\Requests\Workspaces\UpdateWorkspaceRequest;
use App\Models\Workspace;
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
                ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug', 'workspaces.icon', 'workspaces.color', 'workspaces.preferred_domain_id'])
                ->map(fn ($workspace) => [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'slug' => $workspace->slug,
                    'icon' => $workspace->icon,
                    'color' => $workspace->color,
                    'preferred_domain_id' => $workspace->preferred_domain_id,
                    'role' => $workspace->pivot->role,
                ]),
        ]);
    }

    public function current(Request $request, CurrentWorkspace $current): JsonResponse
    {
        $workspace = $current->require();
        $role = $request->user()->roleIn($workspace);

        return response()->json([
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'icon' => $workspace->icon,
                'color' => $workspace->color,
                'preferred_domain_id' => $workspace->preferred_domain_id,
                'role' => $role?->value,
                'can_manage' => $role?->canManageWorkspace() ?? false,
                'can_edit' => $role?->canEditContent() ?? false,
            ],
        ]);
    }

    public function store(StoreWorkspaceRequest $request, CreateWorkspace $workspaces): JsonResponse
    {
        $data = $request->validated();

        $workspace = $workspaces->handle($request->user(), $data['name'], $data['icon'] ?? null, $data['color'] ?? null);

        return response()->json([
            'data' => $workspace->only(['id', 'name', 'slug', 'icon', 'color', 'preferred_domain_id']),
        ], 201);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace, UpdateWorkspace $workspaces): JsonResponse
    {
        $data = $request->validated();

        $workspace = $workspaces->handle($request->user(), $workspace, $data['name'], $data['preferred_domain_id'] ?? null, $data['icon'] ?? null, $data['color'] ?? null);

        return response()->json([
            'data' => $workspace->only(['id', 'name', 'slug', 'icon', 'color', 'preferred_domain_id']),
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, DeleteWorkspace $workspaces): JsonResponse
    {
        $nextWorkspace = $workspaces->handle($request->user(), $workspace);

        return response()->json([
            'message' => 'Workspace deleted.',
            'next_workspace_id' => $nextWorkspace->id,
        ]);
    }
}

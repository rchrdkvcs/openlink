<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->workspaces()
                ->orderBy('name')
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

    public function current(Request $request, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'preferred_domain_id' => $workspace->preferred_domain_id,
                'role' => $context->role($user, $workspace),
                'can_manage' => $context->canManageWorkspace($user, $workspace),
                'can_edit' => $context->canEditWorkspace($user, $workspace),
            ],
        ]);
    }

    public function store(Request $request, WorkspaceManager $workspaces): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $workspace = $workspaces->create($request->user(), $data['name']);

        return response()->json([
            'data' => $workspace->only(['id', 'name', 'slug', 'preferred_domain_id']),
        ], 201);
    }

    public function update(Request $request, WorkspaceContext $context, WorkspaceManager $workspaces): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'preferred_domain_id' => ['nullable', 'integer'],
        ]);

        $workspace = $workspaces->update($workspace, $data['name'], $data['preferred_domain_id'] ?? null);

        return response()->json([
            'data' => $workspace->only(['id', 'name', 'slug', 'preferred_domain_id']),
        ]);
    }

    public function destroy(Request $request, Workspace $workspace, WorkspaceContext $context, WorkspaceManager $workspaces): JsonResponse
    {
        abort_unless($context->role($request->user(), $workspace) === WorkspaceMember::ROLE_OWNER, 403);

        $nextWorkspace = $workspaces->destroy($request->user(), $workspace);

        return response()->json([
            'message' => 'Workspace deleted.',
            'next_workspace_id' => $nextWorkspace->id,
        ]);
    }
}

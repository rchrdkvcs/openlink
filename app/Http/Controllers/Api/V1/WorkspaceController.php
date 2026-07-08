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
use Illuminate\Validation\Rule;

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

    public function current(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'icon' => $workspace->icon,
                'color' => $workspace->color,
                'preferred_domain_id' => $workspace->preferred_domain_id,
                'role' => $access->role($user, $workspace),
                'can_manage' => $access->canManageWorkspace($user, $workspace),
                'can_edit' => $access->canEditWorkspace($user, $workspace),
            ],
        ]);
    }

    public function store(Request $request, CreateWorkspace $workspaces): JsonResponse
    {
        $data = $request->validate($this->rules());

        $workspace = $workspaces->handle($request->user(), $data['name'], $data['icon'] ?? null, $data['color'] ?? null);

        return response()->json([
            'data' => $workspace->only(['id', 'name', 'slug', 'icon', 'color', 'preferred_domain_id']),
        ], 201);
    }

    public function update(Request $request, Workspace $workspace, WorkspaceAccess $access, UpdateWorkspace $workspaces): JsonResponse
    {
        abort_unless($access->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            ...$this->rules(),
            'preferred_domain_id' => ['nullable', 'integer'],
        ]);

        $workspace = $workspaces->handle($workspace, $data['name'], $data['preferred_domain_id'] ?? null, $data['icon'] ?? null, $data['color'] ?? null);

        return response()->json([
            'data' => $workspace->only(['id', 'name', 'slug', 'icon', 'color', 'preferred_domain_id']),
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

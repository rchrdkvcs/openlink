<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        return response()->json(['data' => $workspace->tags()->orderBy('name')->get()]);
    }

    public function store(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireEditableWorkspace($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $tag = Tag::query()->firstOrCreate([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
        ]);

        return response()->json(['data' => $tag], $tag->wasRecentlyCreated ? 201 : 200);
    }
}

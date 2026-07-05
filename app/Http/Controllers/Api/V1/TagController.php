<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Services\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        return response()->json(['data' => $workspace->tags()->orderBy('name')->get()]);
    }

    public function store(Request $request, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canEditWorkspace($request->user(), $workspace), 403);

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

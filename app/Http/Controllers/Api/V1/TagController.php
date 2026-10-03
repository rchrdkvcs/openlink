<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(CurrentWorkspace $current): JsonResponse
    {
        return response()->json(['data' => $current->require()->tags()->orderBy('name')->get()]);
    }

    public function store(Request $request, CurrentWorkspace $current): JsonResponse
    {
        $workspace = $current->require('editContent');

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

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Folder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FolderController extends Controller
{
    public function index(CurrentWorkspace $current): JsonResponse
    {
        return response()->json(['data' => $current->require()->folders()->orderBy('name')->get()]);
    }

    public function store(Request $request, CurrentWorkspace $current): JsonResponse
    {
        $workspace = $current->require('manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder = Folder::create(['workspace_id' => $workspace->id, 'name' => $data['name']]);

        return response()->json(['data' => $folder], 201);
    }

    public function update(Request $request, Folder $folder): JsonResponse
    {
        Gate::authorize('update', $folder);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->update(['name' => $data['name']]);

        return response()->json(['data' => $folder]);
    }

    public function destroy(Folder $folder): JsonResponse
    {
        Gate::authorize('delete', $folder);

        $folder->delete();

        return response()->json(['message' => 'Folder deleted.']);
    }
}

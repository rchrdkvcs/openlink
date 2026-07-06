<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(Request $request, WorkspaceAccess $access): RedirectResponse
    {
        $workspace = $access->requireEditableWorkspace($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        Tag::query()->firstOrCreate([
            'workspace_id' => $workspace->id,
            'name' => $data['name'],
        ]);

        return back();
    }
}

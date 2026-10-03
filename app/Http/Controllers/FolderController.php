<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\Folder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FolderController extends Controller
{
    public function store(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $workspace = $current->require('manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        Folder::create(['workspace_id' => $workspace->id, 'name' => $data['name']]);

        return back();
    }

    public function update(Request $request, Folder $folder): RedirectResponse
    {
        Gate::authorize('update', $folder);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $folder->update(['name' => $data['name']]);

        return back();
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        Gate::authorize('delete', $folder);

        $folder->delete();

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(Request $request, CurrentWorkspace $current): RedirectResponse
    {
        $workspace = $current->require('editContent');

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

<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Services\WorkspaceContext;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function store(Request $request, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canEditWorkspace($request->user(), $workspace), 403);

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

<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceController extends Controller
{
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $workspace = DB::transaction(function () use ($request, $data) {
            $slug = Str::slug($data['name']);
            $base = $slug ?: 'workspace';
            $i = 1;

            while (Workspace::query()->where('slug', $slug)->exists()) {
                $slug = $base.'-'.$i++;
            }

            $workspace = Workspace::create([
                'owner_id' => $request->user()->id,
                'name' => $data['name'],
                'slug' => $slug,
                'settings' => [],
            ]);

            WorkspaceMember::create([
                'workspace_id' => $workspace->id,
                'user_id' => $request->user()->id,
                'role' => WorkspaceMember::ROLE_OWNER,
            ]);

            return $workspace;
        });

        $request->session()->put('workspace_id', $workspace->id);

        return back();
    }

    public function switch(Request $request, Workspace $workspace, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $context->setCurrent($request, $workspace);

        return back();
    }

    public function update(Request $request, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'preferred_domain_id' => ['nullable', 'integer'],
        ]);

        $preferredDomainId = $data['preferred_domain_id'] ?: null;

        if ($preferredDomainId) {
            abort_unless(
                \App\Models\Domain::query()
                    ->whereKey($preferredDomainId)
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id)->orWhere('is_default', true))
                    ->exists(),
                422
            );
        }

        $workspace->update([
            'name' => $data['name'],
            'preferred_domain_id' => $preferredDomainId,
        ]);

        return back();
    }
}

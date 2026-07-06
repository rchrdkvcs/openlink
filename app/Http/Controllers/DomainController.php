<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\DomainManager;
use App\Services\DomainVerificationService;
use App\Services\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function store(Request $request, WorkspaceContext $context, DomainManager $domains): RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255', 'unique:domains,hostname'],
        ]);

        $domains->create($workspace, $data['hostname']);

        return back();
    }

    public function verify(Request $request, Domain $domain, WorkspaceContext $context, DomainVerificationService $verifier): RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $verifier->verify($domain);

        return back();
    }

    public function disable(Request $request, Domain $domain, WorkspaceContext $context, DomainManager $domains): RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $domains->disable($domain);

        return back();
    }

    public function transfer(Request $request, Domain $domain, WorkspaceContext $context, DomainManager $domains): RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);
        abort_if($domain->is_default, 403);

        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $targetWorkspace = $request->user()
            ->workspaces()
            ->where('workspaces.id', $data['workspace_id'])
            ->first();

        abort_unless($targetWorkspace && $context->canManageWorkspace($request->user(), $targetWorkspace), 403);

        $domains->transfer($domain, $workspace, $targetWorkspace);

        return back();
    }

    public function destroy(Request $request, Domain $domain, WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);
        abort_if($domain->is_default, 403);

        $domain->delete();

        return back();
    }
}

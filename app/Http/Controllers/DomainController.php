<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Services\DomainVerificationService;
use App\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DomainController extends Controller
{
    public function store(Request $request, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255', 'unique:domains,hostname'],
        ]);

        $hostname = strtolower(preg_replace('/^https?:\/\//', '', trim($data['hostname'])));
        $hostname = trim($hostname, '/');

        Domain::create([
            'workspace_id' => $workspace->id,
            'hostname' => $hostname,
            'status' => Domain::STATUS_PENDING,
            'verification_token' => Str::random(40),
        ]);

        return back();
    }

    public function verify(Request $request, Domain $domain, WorkspaceContext $context, DomainVerificationService $verifier): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $verifier->verify($domain);

        return back();
    }

    public function disable(Request $request, Domain $domain, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $domain->update([
            'status' => Domain::STATUS_DISABLED,
            'disabled_at' => now(),
        ]);

        return back();
    }

    public function destroy(Request $request, Domain $domain, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);
        abort_if($domain->is_default, 403);

        $domain->delete();

        return back();
    }
}

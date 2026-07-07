<?php

namespace App\Http\Controllers;

use App\Actions\Domains\CreateDomain;
use App\Actions\Domains\DeleteDomain;
use App\Actions\Domains\DisableDomain;
use App\Actions\Domains\DomainPayload;
use App\Actions\Domains\RunDomainChecks;
use App\Actions\Domains\TransferDomain;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    public function create(Request $request, WorkspaceAccess $access): Response
    {
        $access->requireManagedWorkspace($request);

        return Inertia::render('Domains/Setup', ['domain' => null]);
    }

    public function setup(Request $request, Domain $domain, WorkspaceAccess $access, DomainPayload $payload): Response
    {
        $access->requireManagedDomain($request, $domain);

        return Inertia::render('Domains/Setup', ['domain' => $payload->handle($domain)]);
    }

    public function store(Request $request, CreateDomain $domains): RedirectResponse
    {
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255', 'unique:domains,hostname'],
        ]);

        $domain = $domains->handle($request, $data['hostname']);

        return redirect()->route('domains.setup', $domain);
    }

    public function verify(Request $request, Domain $domain, WorkspaceAccess $access, RunDomainChecks $checks): RedirectResponse
    {
        $access->requireManagedDomain($request, $domain);
        $checks->handle($domain);

        return back();
    }

    public function disable(Request $request, Domain $domain, DisableDomain $domains): RedirectResponse
    {
        $domains->handle($request, $domain);

        return back();
    }

    public function transfer(Request $request, Domain $domain, TransferDomain $domains): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $domains->handle($request, $domain, (int) $data['workspace_id']);

        return back();
    }

    public function destroy(Request $request, Domain $domain, DeleteDomain $domains): RedirectResponse
    {
        $domains->handle($request, $domain);

        return redirect()->route('domains.index');
    }
}

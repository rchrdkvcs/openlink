<?php

namespace App\Http\Controllers;

use App\Actions\Domains\CreateDomain;
use App\Actions\Domains\DeleteDomain;
use App\Actions\Domains\DisableDomain;
use App\Actions\Domains\TransferDomain;
use App\Actions\Domains\VerifyDomain;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function store(Request $request, CreateDomain $domains): RedirectResponse
    {
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255', 'unique:domains,hostname'],
        ]);

        $domains->handle($request, $data['hostname']);

        return back();
    }

    public function verify(Request $request, Domain $domain, WorkspaceAccess $access, VerifyDomain $verifier): RedirectResponse
    {
        $access->requireManagedDomain($request, $domain);
        $verifier->handle($domain);

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

        return back();
    }
}

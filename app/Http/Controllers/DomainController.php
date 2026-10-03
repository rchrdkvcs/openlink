<?php

namespace App\Http\Controllers;

use App\Actions\Domains\DeleteDomain;
use App\Actions\Domains\DomainLifecycle;
use App\Actions\Domains\DomainPayload;
use App\Actions\Domains\TransferDomain;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Requests\Domains\StoreDomainRequest;
use App\Models\Domain;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    public function create(CurrentWorkspace $current): Response
    {
        $current->require('manage');

        return Inertia::render('Domains/Setup', ['domain' => null]);
    }

    public function setup(Domain $domain, DomainPayload $payload): Response
    {
        Gate::authorize('manage', $domain);

        return Inertia::render('Domains/Setup', ['domain' => $payload->handle($domain)]);
    }

    public function store(StoreDomainRequest $request, CurrentWorkspace $current, DomainLifecycle $domains): RedirectResponse
    {
        $domain = $domains->register($current->require('manage'), $request->validated('hostname'));

        return redirect()->route('domains.setup', $domain);
    }

    public function verify(Domain $domain, DomainLifecycle $domains): RedirectResponse
    {
        Gate::authorize('manage', $domain);
        $domains->check($domain);

        return back();
    }

    public function disable(Domain $domain, DomainLifecycle $domains): RedirectResponse
    {
        Gate::authorize('manage', $domain);
        $domains->disable($domain);

        return back();
    }

    public function transfer(Request $request, Domain $domain, TransferDomain $domains): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $domains->handle($request->user(), $domain, (int) $data['workspace_id']);

        return back();
    }

    public function destroy(Request $request, Domain $domain, DeleteDomain $domains): RedirectResponse
    {
        $domains->handle($request->user(), $domain);

        return redirect()->route('domains.index');
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Domains\DeleteDomain;
use App\Actions\Domains\DomainLifecycle;
use App\Actions\Domains\DomainPayload;
use App\Actions\Domains\TransferDomain;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\Domains\StoreDomainRequest;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DomainController extends Controller
{
    public function index(CurrentWorkspace $current, DomainPayload $payload): JsonResponse
    {
        return response()->json(['data' => $payload->forWorkspace($current->require())]);
    }

    public function store(StoreDomainRequest $request, CurrentWorkspace $current, DomainLifecycle $domains, DomainPayload $payload): JsonResponse
    {
        $domain = $domains->register($current->require('manage'), $request->validated('hostname'));

        return response()->json(['data' => $payload->handle($domain)], 201);
    }

    public function verify(Domain $domain, DomainLifecycle $domains, DomainPayload $payload): JsonResponse
    {
        Gate::authorize('manage', $domain);

        return response()->json(['data' => $payload->handle($domains->check($domain))]);
    }

    public function disable(Domain $domain, DomainLifecycle $domains, DomainPayload $payload): JsonResponse
    {
        Gate::authorize('manage', $domain);

        return response()->json(['data' => $payload->handle($domains->disable($domain))]);
    }

    public function transfer(Request $request, Domain $domain, TransferDomain $domains, DomainPayload $payload): JsonResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $domain = $domains->handle($request->user(), $domain, (int) $data['workspace_id']);

        return response()->json(['data' => $payload->handle($domain)]);
    }

    public function destroy(Request $request, Domain $domain, DeleteDomain $domains): JsonResponse
    {
        $domains->handle($request->user(), $domain);

        return response()->json(['message' => 'Domain deleted.']);
    }
}

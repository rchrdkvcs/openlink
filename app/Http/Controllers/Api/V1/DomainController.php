<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Domains\CreateDomain;
use App\Actions\Domains\DeleteDomain;
use App\Actions\Domains\DisableDomain;
use App\Actions\Domains\DomainPayload;
use App\Actions\Domains\RunDomainChecks;
use App\Actions\Domains\TransferDomain;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, WorkspacePayloads $data): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        return response()->json(['data' => $data->domains($workspace)]);
    }

    public function store(Request $request, CreateDomain $domains, DomainPayload $payload): JsonResponse
    {
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255', 'unique:domains,hostname'],
        ]);

        $domain = $domains->handle($request, $data['hostname']);

        return response()->json(['data' => $payload->handle($domain)], 201);
    }

    public function verify(Request $request, Domain $domain, WorkspaceAccess $access, RunDomainChecks $checks, DomainPayload $payload): JsonResponse
    {
        $access->requireManagedDomain($request, $domain);
        $domain = $checks->handle($domain);

        return response()->json(['data' => $payload->handle($domain)]);
    }

    public function disable(Request $request, Domain $domain, DisableDomain $domains, DomainPayload $payload): JsonResponse
    {
        $domain = $domains->handle($request, $domain);

        return response()->json(['data' => $payload->handle($domain)]);
    }

    public function transfer(Request $request, Domain $domain, TransferDomain $domains, DomainPayload $payload): JsonResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer'],
        ]);

        $domain = $domains->handle($request, $domain, (int) $data['workspace_id']);

        return response()->json(['data' => $payload->handle($domain)]);
    }

    public function destroy(Request $request, Domain $domain, DeleteDomain $domains): JsonResponse
    {
        $domains->handle($request, $domain);

        return response()->json(['message' => 'Domain deleted.']);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Services\DomainManager;
use App\Services\DomainVerificationService;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainController extends Controller
{
    public function index(Request $request, WorkspaceContext $context, WorkspaceData $data): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        return response()->json(['data' => $data->domains($workspace)]);
    }

    public function store(Request $request, WorkspaceContext $context, DomainManager $domains, DomainVerificationService $verifier): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canManageWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:255', 'unique:domains,hostname'],
        ]);

        $domain = $domains->create($workspace, $data['hostname']);

        return response()->json(['data' => $this->payload($domain, $verifier)], 201);
    }

    public function verify(Request $request, Domain $domain, WorkspaceContext $context, DomainVerificationService $verifier): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $domain = $verifier->verify($domain);

        return response()->json(['data' => $this->payload($domain, $verifier)]);
    }

    public function disable(Request $request, Domain $domain, WorkspaceContext $context, DomainManager $domains, DomainVerificationService $verifier): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $domain = $domains->disable($domain);

        return response()->json(['data' => $this->payload($domain, $verifier)]);
    }

    public function transfer(Request $request, Domain $domain, WorkspaceContext $context, DomainManager $domains, DomainVerificationService $verifier): JsonResponse
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

        $domain = $domains->transfer($domain, $workspace, $targetWorkspace);

        return response()->json(['data' => $this->payload($domain, $verifier)]);
    }

    public function destroy(Request $request, Domain $domain, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $domain->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);
        abort_if($domain->is_default, 403);

        $domain->delete();

        return response()->json(['message' => 'Domain deleted.']);
    }

    /** @return array<string, mixed> */
    private function payload(Domain $domain, DomainVerificationService $verifier): array
    {
        return [
            'id' => $domain->id,
            'hostname' => $domain->hostname,
            'status' => $domain->status,
            'is_default' => $domain->is_default,
            'workspace_id' => $domain->workspace_id,
            'expected_txt' => $verifier->expectedTxtValue($domain),
            'failure_reason' => $domain->failure_reason,
        ];
    }
}

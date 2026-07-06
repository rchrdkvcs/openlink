<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Services\ShortLinkManager;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShortLinkController extends Controller
{
    public function index(Request $request, WorkspaceContext $context, WorkspaceData $data): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        return response()->json(['data' => $data->links($workspace, $request->user())]);
    }

    public function show(Request $request, ShortLink $shortLink, WorkspaceContext $context, WorkspaceData $data): JsonResponse
    {
        $workspace = $context->current($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canViewShortLink($request->user(), $shortLink), 403);

        return response()->json(['data' => $data->linkPayload($shortLink)]);
    }

    public function store(Request $request, WorkspaceContext $context, ShortLinkManager $shortLinks, WorkspaceData $workspaceData): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canEditWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'domain_id' => ['nullable', 'integer'],
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'slug' => ['nullable', 'string', 'max:512'],
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => ['nullable', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        // API convenience: fall back to the workspace's preferred domain,
        // then the instance default domain, when no domain_id is given.
        $domainId = $data['domain_id']
            ?? $workspace->preferred_domain_id
            ?? $workspaceData->defaultDomain()?->id;

        abort_unless($domainId, 422, 'No domain available for this workspace.');

        $domain = $shortLinks->domainForWorkspace((int) $domainId, $workspace->id);
        abort_unless($domain, 422, 'Domain does not belong to this workspace.');
        abort_unless($domain->isUsable(), 422, 'Domain is not verified or is disabled.');

        $folder = filled($data['folder_id'] ?? null) ? Folder::query()->find($data['folder_id']) : null;
        abort_if($folder && ! $context->canEditFolder($request->user(), $folder), 403);

        $shortLink = $shortLinks->create($workspace, $domain, $folder, $data);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)], 201);
    }

    public function update(Request $request, ShortLink $shortLink, WorkspaceContext $context, ShortLinkManager $shortLinks, WorkspaceData $workspaceData): JsonResponse
    {
        $workspace = $context->current($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canEditShortLink($request->user(), $shortLink), 403);

        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => ['required', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
        ]);

        $folder = filled($data['folder_id'] ?? null) ? Folder::query()->find($data['folder_id']) : null;
        abort_if($folder && ! $context->canEditFolder($request->user(), $folder), 403);

        $shortLink = $shortLinks->update($shortLink, $folder, $data, $request->has('password'));

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)]);
    }

    public function move(Request $request, ShortLink $shortLink, WorkspaceContext $context, WorkspaceData $workspaceData): JsonResponse
    {
        $workspace = $context->current($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canEditShortLink($request->user(), $shortLink), 403);

        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $folder = filled($data['folder_id'] ?? null) ? Folder::query()->find($data['folder_id']) : null;
        abort_if($folder && ! $context->canEditFolder($request->user(), $folder), 403);

        $shortLink->update(['folder_id' => $folder?->id]);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink->fresh(['domain', 'folder', 'tags', 'qrCodes']))]);
    }

    public function archive(Request $request, ShortLink $shortLink, WorkspaceContext $context, WorkspaceData $workspaceData): JsonResponse
    {
        $workspace = $context->current($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canEditShortLink($request->user(), $shortLink), 403);

        $shortLink->update(['archived_at' => now()]);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)]);
    }

    public function destroy(Request $request, ShortLink $shortLink, WorkspaceContext $context): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $shortLink->delete();

        return response()->json(['message' => 'Short link deleted.']);
    }
}

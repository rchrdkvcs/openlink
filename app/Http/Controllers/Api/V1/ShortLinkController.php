<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShortLinks\ArchiveShortLink;
use App\Actions\ShortLinks\CreateShortLink;
use App\Actions\ShortLinks\DeleteShortLink;
use App\Actions\ShortLinks\MoveShortLink;
use App\Actions\ShortLinks\UpdateShortLink;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Http\Controllers\Controller;
use App\Models\ShortLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ShortLinkController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, WorkspacePayloads $data): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        return response()->json(['data' => $data->links($workspace, $request->user())]);
    }

    public function show(Request $request, ShortLink $shortLink, WorkspaceAccess $access, WorkspacePayloads $data): JsonResponse
    {
        $access->requireViewableShortLink($request, $shortLink);

        return response()->json(['data' => $data->linkPayload($shortLink)]);
    }

    public function store(Request $request, WorkspaceAccess $access, CreateShortLink $shortLinks, WorkspacePayloads $workspaceData): JsonResponse
    {
        $workspace = $access->requireEditableWorkspace($request);

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
        $fallbackDomain = $workspace->preferredDomain ?? $workspaceData->defaultDomain();
        abort_unless(($data['domain_id'] ?? null) || $fallbackDomain, 422, 'No domain available for this workspace.');

        $shortLink = $shortLinks->handle($workspace, $request->user(), $data, $fallbackDomain);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)], 201);
    }

    public function update(Request $request, ShortLink $shortLink, WorkspaceAccess $access, UpdateShortLink $shortLinks, WorkspacePayloads $workspaceData): JsonResponse
    {
        $workspace = $access->requireEditableShortLink($request, $shortLink);

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

        $shortLink = $shortLinks->handle($request, $shortLink, $data);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)]);
    }

    public function move(Request $request, ShortLink $shortLink, WorkspaceAccess $access, MoveShortLink $move, WorkspacePayloads $workspaceData): JsonResponse
    {
        $workspace = $access->requireEditableShortLink($request, $shortLink);

        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $shortLink = $move->handle($request, $shortLink, $data['folder_id'] ?? null);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink->fresh(['domain', 'folder', 'tags', 'qrCodes']))]);
    }

    public function archive(Request $request, ShortLink $shortLink, ArchiveShortLink $archive, WorkspacePayloads $workspaceData): JsonResponse
    {
        $shortLink = $archive->handle($request, $shortLink);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)]);
    }

    public function destroy(Request $request, ShortLink $shortLink, DeleteShortLink $delete): JsonResponse
    {
        $delete->handle($request, $shortLink);

        return response()->json(['message' => 'Short link deleted.']);
    }
}

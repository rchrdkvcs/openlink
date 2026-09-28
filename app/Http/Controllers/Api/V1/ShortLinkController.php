<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Actions\Workspaces\WorkspaceViewFactory;
use App\Http\Controllers\Controller;
use App\Models\ShortLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShortLinkController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, WorkspacePayloads $data, WorkspaceViewFactory $views): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:active,scheduled,expired,disabled,archived'],
            'tag' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = $data->linksPage($views->make($workspace, $request->user()), [
            ...$filters,
            'status' => $filters['status'] ?? 'all',
        ]);

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, ShortLink $shortLink, WorkspaceAccess $access, WorkspacePayloads $data): JsonResponse
    {
        $access->requireViewableShortLink($request, $shortLink);

        return response()->json(['data' => $data->linkPayload($shortLink)]);
    }

    public function store(Request $request, ShortLinkMutation $shortLinks, WorkspacePayloads $workspaceData): JsonResponse
    {
        $shortLink = $shortLinks->create($request);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)], 201);
    }

    public function update(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks, WorkspacePayloads $workspaceData): JsonResponse
    {
        $shortLink = $shortLinks->update($request, $shortLink);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)]);
    }

    public function move(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks, WorkspacePayloads $workspaceData): JsonResponse
    {
        $shortLink = $shortLinks->move($request, $shortLink);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink->fresh(['domain', 'folder', 'tags', 'qrCodes']))]);
    }

    public function archive(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks, WorkspacePayloads $workspaceData): JsonResponse
    {
        $shortLink = $shortLinks->archive($request, $shortLink);

        return response()->json(['data' => $workspaceData->linkPayload($shortLink)]);
    }

    public function destroy(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): JsonResponse
    {
        $shortLinks->delete($request, $shortLink);

        return response()->json(['message' => 'Short link deleted.']);
    }
}

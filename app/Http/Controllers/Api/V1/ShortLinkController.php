<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShortLinks\LinksQuery;
use App\Actions\ShortLinks\RevisionPrecondition;
use App\Actions\ShortLinks\ShortLinkMutation;
use App\Actions\ShortLinks\ShortLinkPayload;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShortLinks\MoveShortLinkRequest;
use App\Http\Requests\ShortLinks\StoreShortLinkRequest;
use App\Http\Requests\ShortLinks\UpdateShortLinkRequest;
use App\Models\ShortLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class ShortLinkController extends Controller
{
    public function index(Request $request, CurrentWorkspace $current, LinksQuery $links): JsonResponse
    {
        $workspace = $current->require();
        $filters = $request->validate(Arr::except(LinksQuery::rules(), 'folder'));
        $page = $links->paginate($workspace, [
            ...$filters,
            'status' => $filters['status'] ?? LinksQuery::ALL_STATUSES,
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

    public function __construct(private readonly RevisionPrecondition $precondition) {}

    public function show(ShortLink $shortLink, ShortLinkPayload $payload): JsonResponse
    {
        Gate::authorize('view', $shortLink);

        return $this->precondition->tag(response()->json(['data' => $payload->handle($shortLink)]), $shortLink);
    }

    public function store(StoreShortLinkRequest $request, ShortLinkMutation $shortLinks, ShortLinkPayload $payload): JsonResponse
    {
        $shortLink = $shortLinks->create($request->user(), $request->workspace(), $request->validated());

        return $this->precondition->tag(response()->json(['data' => $payload->handle($shortLink)], 201), $shortLink);
    }

    public function update(UpdateShortLinkRequest $request, ShortLink $shortLink, ShortLinkMutation $shortLinks, ShortLinkPayload $payload): JsonResponse
    {
        $data = $request->validated();
        $data['expected_revision'] = $this->precondition->expected($request, $shortLink, isset($data['expected_revision']) ? (int) $data['expected_revision'] : null);
        $shortLink = $shortLinks->update($request->user(), $shortLink, $data);

        return $this->precondition->tag(response()->json(['data' => $payload->handle($shortLink)]), $shortLink);
    }

    public function move(MoveShortLinkRequest $request, ShortLink $shortLink, ShortLinkMutation $shortLinks, ShortLinkPayload $payload): JsonResponse
    {
        $shortLink = $shortLinks->move($request->user(), $shortLink, $request->folderId(), $this->precondition->expected($request, $shortLink));

        return $this->precondition->tag(response()->json(['data' => $payload->handle($shortLink->fresh(['domain', 'folder', 'tags', 'qrCodes']))]), $shortLink);
    }

    public function archive(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks, ShortLinkPayload $payload): JsonResponse
    {
        $shortLink = $shortLinks->archive($request->user(), $shortLink, $this->precondition->expected($request, $shortLink));

        return $this->precondition->tag(response()->json(['data' => $payload->handle($shortLink)]), $shortLink);
    }

    public function destroy(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): JsonResponse
    {
        $shortLinks->delete($request->user(), $shortLink);

        return response()->json(['message' => 'Short link deleted.']);
    }
}

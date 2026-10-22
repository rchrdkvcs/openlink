<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShortLinks\LinkRevisionPayload;
use App\Actions\ShortLinks\LinkRevisionRestore;
use App\Actions\ShortLinks\RevisionPrecondition;
use App\Actions\ShortLinks\ShortLinkPayload;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShortLinks\RestoreShortLinkRevisionRequest;
use App\Models\ShortLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShortLinkRevisionController extends Controller
{
    public function index(Request $request, ShortLink $shortLink, LinkRevisionPayload $payload): JsonResponse
    {
        Gate::authorize('view', $shortLink);
        $page = $request->validate(['page' => ['nullable', 'integer', 'min:1']])['page'] ?? 1;

        return response()->json($payload->page($shortLink, (int) $page));
    }

    public function show(ShortLink $shortLink, int $revision, LinkRevisionPayload $payload): JsonResponse
    {
        Gate::authorize('view', $shortLink);

        return response()->json(['data' => $payload->detail($shortLink, $shortLink->findRevision($revision))]);
    }

    public function restore(RestoreShortLinkRevisionRequest $request, ShortLink $shortLink, LinkRevisionRestore $restore, ShortLinkPayload $payload, RevisionPrecondition $precondition): JsonResponse
    {
        $shortLink = $restore->restore($request->user(), $shortLink, $request->revision(), $request->expectedRevision());

        return $precondition->tag(response()->json(['data' => $payload->handle($shortLink->fresh())]), $shortLink);
    }
}

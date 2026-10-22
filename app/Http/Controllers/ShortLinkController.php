<?php

namespace App\Http\Controllers;

use App\Actions\ShortLinks\BulkShortLinkMutation;
use App\Actions\ShortLinks\PreviewSmartRouting;
use App\Actions\ShortLinks\ShortLinkMutation;
use App\Actions\ShortLinks\ShortLinkPayload;
use App\Http\Requests\ShortLinks\BulkShortLinkRequest;
use App\Http\Requests\ShortLinks\MoveShortLinkRequest;
use App\Http\Requests\ShortLinks\PreviewSmartRoutingRequest;
use App\Http\Requests\ShortLinks\StoreShortLinkRequest;
use App\Http\Requests\ShortLinks\UnarchiveShortLinkRequest;
use App\Http\Requests\ShortLinks\UpdateShortLinkRequest;
use App\Models\ShortLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShortLinkController extends Controller
{
    public function store(StoreShortLinkRequest $request, ShortLinkMutation $shortLinks, ShortLinkPayload $payload): RedirectResponse
    {
        $link = $shortLinks->create($request->user(), $request->workspace(), $request->validated());

        Inertia::flash('createdLink', $payload->handle($link));

        return back();
    }

    public function update(UpdateShortLinkRequest $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->update($request->user(), $shortLink, $request->validated());

        return back();
    }

    public function routingPreview(PreviewSmartRoutingRequest $request, ShortLink $shortLink, PreviewSmartRouting $preview): JsonResponse
    {
        return response()->json($preview->handle($shortLink, $request->validated()));
    }

    public function archive(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->archive($request->user(), $shortLink);

        return back();
    }

    public function unarchive(UnarchiveShortLinkRequest $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->unarchive($request->user(), $shortLink, (int) $request->validated('expected_revision'));

        return back();
    }

    public function move(MoveShortLinkRequest $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->move($request->user(), $shortLink, $request->folderId());

        return back();
    }

    public function bulk(BulkShortLinkRequest $request, BulkShortLinkMutation $bulk): RedirectResponse
    {
        $bulk->apply(
            $request->user(),
            $request->workspace(),
            $request->bulkAction(),
            $request->ids(),
            $request->folderId(),
            $request->tags(),
        );

        return back();
    }

    public function destroy(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->delete($request->user(), $shortLink);

        return back();
    }
}

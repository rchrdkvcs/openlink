<?php

namespace App\Http\Controllers;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Actions\ShortLinks\ShortLinkPayload;
use App\Http\Requests\ShortLinks\MoveShortLinkRequest;
use App\Http\Requests\ShortLinks\StoreShortLinkRequest;
use App\Http\Requests\ShortLinks\UpdateShortLinkRequest;
use App\Models\ShortLink;
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

    public function archive(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->archive($request->user(), $shortLink);

        return back();
    }

    public function move(MoveShortLinkRequest $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->move($request->user(), $shortLink, $request->folderId());

        return back();
    }

    public function destroy(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->delete($request->user(), $shortLink);

        return back();
    }
}

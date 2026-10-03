<?php

namespace App\Http\Controllers;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Models\ShortLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShortLinkController extends Controller
{
    public function store(Request $request, ShortLinkMutation $shortLinks, WorkspacePayloads $payloads): RedirectResponse
    {
        $link = $shortLinks->create($request);

        Inertia::flash('createdLink', $payloads->linkPayload($link));

        return back();
    }

    public function update(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->update($request, $shortLink);

        return back();
    }

    public function archive(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->archive($request, $shortLink);

        return back();
    }

    public function move(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->move($request, $shortLink);

        return back();
    }

    public function destroy(Request $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->delete($request, $shortLink);

        return back();
    }
}

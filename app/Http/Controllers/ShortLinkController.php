<?php

namespace App\Http\Controllers;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Models\ShortLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShortLinkController extends Controller
{
    public function store(Request $request, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $shortLinks->create($request);

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

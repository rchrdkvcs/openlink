<?php

namespace App\Http\Controllers;

use App\Actions\ShortLinks\ShortLinkMutation;
use App\Actions\ShortLinks\ShortLinkPayload;
use App\Http\Requests\ShortLinks\UpdateShortLinkRoutingRequest;
use App\Models\ShortLink;
use App\Services\ShortLinks\SmartRouting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ShortLinkRoutingController extends Controller
{
    public function edit(ShortLink $shortLink, ShortLinkPayload $payload, SmartRouting $routing): Response
    {
        Gate::authorize('view', $shortLink);

        return Inertia::render('Links/Routing', [
            'link' => $payload->handle($shortLink),
            'routingSchema' => $routing->editorPayload(),
        ]);
    }

    public function update(UpdateShortLinkRoutingRequest $request, ShortLink $shortLink, ShortLinkMutation $shortLinks): RedirectResponse
    {
        $data = $request->validated();
        $saved = $shortLinks->updateRouting($request->user(), $shortLink, $data['destination_url'], $data['routing_rules'], (int) $data['expected_revision']);
        Inertia::flash('routingSaved', [
            'link_id' => $saved->id,
            'expected_revision' => (int) $data['expected_revision'],
            'revision' => $saved->revision,
        ]);

        return back();
    }
}

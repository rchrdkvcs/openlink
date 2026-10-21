<?php

namespace App\Http\Controllers;

use App\Actions\DestinationHealth\DestinationChecks;
use App\Actions\DestinationHealth\DestinationHealthPayload;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\DestinationCheck;
use App\Models\ShortLink;
use App\Services\InstanceSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DestinationHealthController extends Controller
{
    public function index(CurrentWorkspace $current, DestinationHealthPayload $payload, InstanceSettings $settings): Response
    {
        return Inertia::render('Links/Issues', [
            'issues' => $payload->issues($current->require()),
            'monitoringEnabled' => (bool) $settings->get('destination_monitoring'),
        ]);
    }

    public function checkShortLink(Request $request, ShortLink $shortLink, DestinationChecks $checks): RedirectResponse
    {
        $checks->requestForShortLink($request->user(), $shortLink);

        return back();
    }

    public function retry(Request $request, DestinationCheck $destinationCheck, DestinationChecks $checks): RedirectResponse
    {
        $checks->retry($request->user(), $destinationCheck);

        return back();
    }
}

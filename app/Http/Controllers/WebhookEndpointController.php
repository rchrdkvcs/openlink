<?php

namespace App\Http\Controllers;

use App\Actions\Webhooks\WebhookEndpointMutation;
use App\Actions\Webhooks\WebhookEndpointPayload;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Enums\WebhookEventType;
use App\Http\Requests\Webhooks\WebhookEndpointRequest;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookEndpointController extends Controller
{
    public function index(Request $request, CurrentWorkspace $current): Response|RedirectResponse
    {
        $workspace = $current->require();

        if ($request->user()->cannot('manage', $workspace)) {
            return redirect()->route('profile.edit');
        }

        return Inertia::render('Webhooks/Index', [
            'endpoints' => WebhookEndpointPayload::forWorkspace($workspace),
            'eventTypes' => WebhookEventType::options(),
            'historyLimit' => WebhookEndpointPayload::HISTORY_LIMIT,
            'retentionDays' => (int) config('openlink.webhooks.retention_days'),
        ]);
    }

    public function store(WebhookEndpointRequest $request, CurrentWorkspace $current, WebhookEndpointMutation $endpoints): RedirectResponse
    {
        $endpoint = $endpoints->create($request->user(), $current->require(), $request->validated('url'), $request->events());

        Inertia::flash('webhookSecret', ['endpoint_id' => $endpoint->id, 'secret' => $endpoint->secret]);

        return back();
    }

    public function update(WebhookEndpointRequest $request, WebhookEndpoint $webhookEndpoint, WebhookEndpointMutation $endpoints): RedirectResponse
    {
        $endpoints->update($request->user(), $webhookEndpoint, $request->validated('url'), $request->events(), $request->isEnabled());

        return back();
    }

    public function rotateSecret(Request $request, WebhookEndpoint $webhookEndpoint, WebhookEndpointMutation $endpoints): RedirectResponse
    {
        $secret = $endpoints->rotateSecret($request->user(), $webhookEndpoint);

        Inertia::flash('webhookSecret', ['endpoint_id' => $webhookEndpoint->id, 'secret' => $secret]);

        return back();
    }

    public function test(Request $request, WebhookEndpoint $webhookEndpoint, WebhookEndpointMutation $endpoints): RedirectResponse
    {
        $endpoints->sendTest($request->user(), $webhookEndpoint);

        return back();
    }

    public function retry(Request $request, WebhookDelivery $webhookDelivery, WebhookEndpointMutation $endpoints): RedirectResponse
    {
        $endpoints->retry($request->user(), $webhookDelivery);

        return back();
    }

    public function destroy(Request $request, WebhookEndpoint $webhookEndpoint, WebhookEndpointMutation $endpoints): RedirectResponse
    {
        $endpoints->delete($request->user(), $webhookEndpoint);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Domains\DomainPayload;
use App\Actions\InviteLinks\InviteLinkPayload;
use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\CurrentWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request, CurrentWorkspace $current, DomainPayload $domains): Response|RedirectResponse
    {
        $user = $request->user();
        $hasWorkspace = $user->workspaceMemberships()->exists();

        if ($hasWorkspace && ! $request->session()->get('onboarding.active')) {
            return redirect()->route('dashboard');
        }

        if (! $hasWorkspace) {
            return Inertia::render('Onboarding/Index', [
                'workspace' => null,
                'domains' => [],
                'inviteLinks' => [],
                'firstLink' => null,
            ]);
        }

        $workspace = $current->require();
        $firstLink = $workspace->shortLinks()->whereHas('domain')->with('domain')->oldest()->first();

        return Inertia::render('Onboarding/Index', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'domains' => $domains->forWorkspace($workspace),
            'inviteLinks' => InviteLinkPayload::active($workspace),
            'firstLink' => $firstLink ? [
                'short_url' => $firstLink->shortUrl(),
                'destination_url' => $firstLink->destination_url,
            ] : null,
        ]);
    }

    public function storeWorkspace(Request $request, CreateWorkspace $workspaces, CurrentWorkspace $current): RedirectResponse
    {
        if ($request->user()->workspaceMemberships()->exists()) {
            return redirect()->route('onboarding.show');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $current->select($workspaces->handle($request->user(), $data['name']));
        $request->session()->put('onboarding.active', true);

        return redirect()->route('onboarding.show');
    }

    public function complete(Request $request): RedirectResponse
    {
        $request->session()->forget('onboarding.active');

        return redirect()->route('dashboard');
    }
}

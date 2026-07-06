<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request, WorkspaceAccess $access, WorkspacePayloads $data): Response|RedirectResponse
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
                'hasLink' => false,
            ]);
        }

        $workspace = $access->requireCurrent($request);

        return Inertia::render('Onboarding/Index', [
            'workspace' => $workspace->only(['id', 'name', 'slug']),
            'domains' => $data->domains($workspace),
            'inviteLinks' => $data->inviteLinks($workspace),
            'hasLink' => $workspace->shortLinks()->exists(),
        ]);
    }

    public function storeWorkspace(Request $request, CreateWorkspace $workspaces, WorkspaceAccess $access): RedirectResponse
    {
        if ($request->user()->workspaceMemberships()->exists()) {
            return redirect()->route('onboarding.show');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $workspace = $workspaces->handle($request->user(), $data['name']);

        $access->selectCurrent($request, $workspace);
        $request->session()->put('onboarding.active', true);

        return redirect()->route('onboarding.show');
    }

    public function complete(Request $request): RedirectResponse
    {
        $request->session()->forget('onboarding.active');

        return redirect()->route('dashboard');
    }
}

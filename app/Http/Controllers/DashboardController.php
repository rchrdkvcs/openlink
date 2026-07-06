<?php

namespace App\Http\Controllers;

use App\Services\InstanceSettings;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function overview(Request $request, WorkspaceContext $context, InstanceSettings $settings, WorkspaceData $data): Response
    {
        return Inertia::render('Dashboard', $this->pageProps($request, $context, $settings, $data));
    }

    public function links(Request $request, WorkspaceContext $context, InstanceSettings $settings, WorkspaceData $data): Response
    {
        return Inertia::render('Links/Index', $this->pageProps($request, $context, $settings, $data));
    }

    public function domains(Request $request, WorkspaceContext $context, InstanceSettings $settings, WorkspaceData $data): Response
    {
        return Inertia::render('Domains/Index', $this->pageProps($request, $context, $settings, $data));
    }

    public function members(Request $request, WorkspaceContext $context, InstanceSettings $settings, WorkspaceData $data): Response
    {
        return Inertia::render('Members/Index', $this->pageProps($request, $context, $settings, $data));
    }

    public function workspaces(Request $request, WorkspaceContext $context, InstanceSettings $settings, WorkspaceData $data): Response
    {
        return Inertia::render('Workspaces/Index', $this->pageProps($request, $context, $settings, $data));
    }

    public function settings(Request $request, WorkspaceContext $context, InstanceSettings $settings, WorkspaceData $data): Response
    {
        return Inertia::render('Settings/Index', $this->pageProps($request, $context, $settings, $data));
    }

    private function pageProps(Request $request, WorkspaceContext $context, InstanceSettings $settings, WorkspaceData $data): array
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        $user = $request->user();

        return [
            'currentWorkspace' => $workspace->only(['id', 'name', 'slug', 'preferred_domain_id']),
            'workspaces' => $user->workspaces()->orderBy('name')->get(['workspaces.id', 'workspaces.name', 'workspaces.slug']),
            'role' => $context->role($user, $workspace),
            'canManageWorkspace' => $context->canManageWorkspace($user, $workspace),
            'canEditWorkspace' => $context->canEditWorkspace($user, $workspace),
            'domains' => $data->domains($workspace),
            'folders' => $data->folders($workspace, $user),
            'members' => $workspace->members()->with('user:id,name,email')->orderBy('role')->get(),
            'invitations' => $workspace->invitations()->latest()->get(),
            'tags' => $workspace->tags()->orderBy('name')->get(),
            'links' => $data->links($workspace, $user),
            'analytics' => $data->analytics($workspace),
            'settings' => $user->is_instance_admin ? $settings->all() : [],
        ];
    }
}

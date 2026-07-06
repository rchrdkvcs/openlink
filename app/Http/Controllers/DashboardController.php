<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\InstanceSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function overview(Request $request, WorkspaceAccess $access, InstanceSettings $settings, WorkspacePayloads $data, BuildAnalyticsReport $reporter): Response
    {
        $workspace = $access->requireCurrent($request);

        $filters = AnalyticsFilters::fromRequest($request);
        $accessibleLinkIds = $reporter->accessibleLinkIds($workspace, $request->user());

        return Inertia::render('Dashboard', [
            ...$this->pageProps($request, $access, $settings, $data),
            'analytics' => [
                'range' => ['preset' => $filters->range, 'bucket' => $filters->bucketUnit()],
                'summary' => $reporter->summary($workspace, $filters, $accessibleLinkIds),
                'timeseries' => $reporter->timeseries($workspace, $filters, $accessibleLinkIds),
                'top_links' => $reporter->topLinks($workspace, $filters, $accessibleLinkIds, 5),
            ],
        ]);
    }

    public function links(Request $request, WorkspaceAccess $access, InstanceSettings $settings, WorkspacePayloads $data): Response
    {
        return Inertia::render('Links/Index', $this->pageProps($request, $access, $settings, $data));
    }

    public function domains(Request $request, WorkspaceAccess $access, InstanceSettings $settings, WorkspacePayloads $data): Response
    {
        return Inertia::render('Domains/Index', $this->pageProps($request, $access, $settings, $data));
    }

    public function members(Request $request, WorkspaceAccess $access, InstanceSettings $settings, WorkspacePayloads $data): Response
    {
        return Inertia::render('Members/Index', $this->pageProps($request, $access, $settings, $data));
    }

    public function workspaces(Request $request, WorkspaceAccess $access, InstanceSettings $settings, WorkspacePayloads $data): Response
    {
        return Inertia::render('Workspaces/Index', $this->pageProps($request, $access, $settings, $data));
    }

    public function settings(Request $request, WorkspaceAccess $access, InstanceSettings $settings, WorkspacePayloads $data): Response
    {
        return Inertia::render('Settings/Index', $this->pageProps($request, $access, $settings, $data));
    }

    private function pageProps(Request $request, WorkspaceAccess $access, InstanceSettings $settings, WorkspacePayloads $data): array
    {
        $workspace = $access->requireCurrent($request);

        $user = $request->user();
        $canManage = $access->canManageWorkspace($user, $workspace);
        $role = $access->role($user, $workspace);

        return [
            'currentWorkspace' => $workspace->only(['id', 'name', 'slug', 'preferred_domain_id']),
            'workspaces' => $user->workspaces()
                ->orderBy('workspaces.created_at')
                ->orderBy('workspaces.id')
                ->get(['workspaces.id', 'workspaces.name', 'workspaces.slug']),
            'role' => $role,
            'canManageWorkspace' => $canManage,
            'canEditWorkspace' => $access->canEditWorkspace($user, $workspace),
            'domains' => $data->domains($workspace),
            'folders' => $data->folders($workspace, $user),
            'members' => $workspace->members()->with('user:id,name,email')->orderBy('role')->get(),
            'inviteLinks' => $canManage ? $data->inviteLinks($workspace) : [],
            'tags' => $workspace->tags()->orderBy('name')->get(),
            'links' => $data->links($workspace, $user),
            'settings' => $user->is_instance_admin ? $settings->all() : [],
        ];
    }
}

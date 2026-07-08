<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Actions\Pages\DashboardPagePayload;
use App\Actions\Pages\DomainsPagePayload;
use App\Actions\Pages\LinksPagePayload;
use App\Actions\Pages\MembersPagePayload;
use App\Actions\Pages\SettingsPagePayload;
use App\Actions\Pages\WorkspacesPagePayload;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Services\Analytics\AnalyticsFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function overview(Request $request, WorkspaceAccess $access, BuildAnalyticsReport $reporter, DashboardPagePayload $payload): Response
    {
        $workspace = $access->requireCurrent($request);

        $filters = AnalyticsFilters::fromRequest($request);
        $accessibleLinkIds = $reporter->accessibleLinkIds($workspace, $request->user());

        return Inertia::render('Dashboard', [
            ...$payload->handle($workspace, $request->user()),
            'analytics' => [
                'range' => ['preset' => $filters->range, 'bucket' => $filters->bucketUnit()],
                'summary' => $reporter->summary($workspace, $filters, $accessibleLinkIds),
                'timeseries' => $reporter->timeseries($workspace, $filters, $accessibleLinkIds),
                'top_links' => $reporter->topLinks($workspace, $filters, $accessibleLinkIds, 5),
            ],
        ]);
    }

    public function links(Request $request, WorkspaceAccess $access, LinksPagePayload $payload): Response
    {
        $workspace = $access->requireCurrent($request);

        return Inertia::render('Links/Index', $payload->handle($workspace, $request->user()));
    }

    public function domains(Request $request, WorkspaceAccess $access, DomainsPagePayload $payload): Response
    {
        $workspace = $access->requireCurrent($request);

        return Inertia::render('Domains/Index', $payload->handle($workspace, $request->user()));
    }

    public function members(Request $request, WorkspaceAccess $access, MembersPagePayload $payload): Response
    {
        $workspace = $access->requireCurrent($request);

        return Inertia::render('Members/Index', $payload->handle($workspace, $request->user()));
    }

    public function workspaces(Request $request, WorkspaceAccess $access, WorkspacesPagePayload $payload): Response
    {
        $workspace = $access->requireCurrent($request);

        return Inertia::render('Workspaces/Index', $payload->handle($workspace, $request->user()));
    }

    public function settings(Request $request, WorkspaceAccess $access, SettingsPagePayload $payload): Response
    {
        $workspace = $access->requireCurrent($request);

        return Inertia::render('Settings/Index', $payload->handle($workspace, $request->user()));
    }
}

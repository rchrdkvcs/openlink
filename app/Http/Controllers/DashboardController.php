<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Actions\Domains\DomainPayload;
use App\Actions\InviteLinks\InviteLinkPayload;
use App\Actions\Members\MemberRows;
use App\Actions\ShortLinks\LinksQuery;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\InstanceSettings;
use App\Services\ShortLinks\SmartRouting;
use App\Services\UpdateStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function overview(Request $request, CurrentWorkspace $current, BuildAnalyticsReport $reporter, LinksQuery $links, DomainPayload $domains): Response
    {
        $workspace = $current->require();
        $filters = AnalyticsFilters::fromRequest($request);

        return Inertia::render('Dashboard', [
            'domains' => $domains->forWorkspace($workspace),
            'folders' => $workspace->folders()->orderBy('name')->get(),
            'linkCounts' => $links->counts($workspace),
            'recentLinks' => $links->recent($workspace, 6),
            'analytics' => [
                'range' => ['preset' => $filters->range, 'bucket' => $filters->bucketUnit()],
                'summary' => $reporter->summary($workspace, $filters),
                'timeseries' => $reporter->timeseries($workspace, $filters),
                'top_links' => $reporter->topLinks($workspace, $filters, 5),
            ],
        ]);
    }

    public function links(Request $request, CurrentWorkspace $current, LinksQuery $links, DomainPayload $domains, SmartRouting $routing): Response
    {
        $workspace = $current->require();
        $filters = $request->validate(LinksQuery::rules());
        $page = $links->paginate($workspace, $filters);

        return Inertia::render('Links/Index', [
            'domains' => $domains->forWorkspace($workspace),
            'folders' => $workspace->folders()->orderBy('name')->get(),
            'tags' => $workspace->tags()->orderBy('name')->get(),
            'links' => $page->items(),
            'linksPagination' => LinksQuery::pagination($page),
            'filters' => LinksQuery::filterState($filters),
            'routingSchema' => $routing->editorPayload(),
        ]);
    }

    public function domains(CurrentWorkspace $current, DomainPayload $domains): Response
    {
        return Inertia::render('Domains/Index', [
            'domains' => $domains->forWorkspace($current->require()),
        ]);
    }

    public function members(Request $request, CurrentWorkspace $current): Response
    {
        $workspace = $current->require();

        return Inertia::render('Members/Index', [
            'members' => MemberRows::for($workspace),
            'inviteLinks' => $request->user()->can('manage', $workspace) ? InviteLinkPayload::active($workspace) : [],
        ]);
    }

    public function settings(Request $request, CurrentWorkspace $current, InstanceSettings $settings, UpdateStatus $updates): Response
    {
        $current->require();
        $isAdmin = $request->user()->can('administer-instance');

        return Inertia::render('Settings/Index', [
            'settings' => $isAdmin ? $settings->all() : [],
            'updateStatus' => $isAdmin ? $updates->get() : null,
        ]);
    }
}

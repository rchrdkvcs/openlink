<?php

namespace App\Http\Controllers;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Actions\Pages\WorkspaceShellPayload;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\BioPage;
use App\Services\Analytics\AnalyticsFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BioPageAnalyticsController extends Controller
{
    public function __invoke(
        Request $request,
        BioPage $bioPage,
        WorkspaceAccess $access,
        BuildAnalyticsReport $reporter,
        WorkspaceShellPayload $shell,
    ): Response {
        $workspace = $access->requireCurrent($request);
        abort_unless($bioPage->workspace_id === $workspace->id, 403);
        Gate::authorize('view', $bioPage);

        $filters = AnalyticsFilters::fromRequest($request)->forBioPage($bioPage->id);

        return Inertia::render('BioPages/Analytics', [
            ...$shell->handle($workspace, $request->user()),
            'bioPage' => [
                'id' => $bioPage->id,
                'displayName' => $bioPage->draft['displayName'] ?? 'Bio Page',
                'status' => $bioPage->isPublished() ? 'published' : 'draft',
            ],
            'report' => $reporter->report($workspace, $filters),
            'filters' => $filters->toQuery() + ['range' => $filters->range],
        ]);
    }
}

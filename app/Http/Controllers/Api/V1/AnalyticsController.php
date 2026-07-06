<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, BuildAnalyticsReport $reporter): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        $filters = AnalyticsFilters::fromRequest($request);
        $accessibleLinkIds = $reporter->accessibleLinkIds($workspace, $request->user());

        return response()->json([
            'data' => $reporter->report($workspace, $filters, $accessibleLinkIds),
        ]);
    }
}

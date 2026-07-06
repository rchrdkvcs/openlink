<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsFilters;
use App\Services\Analytics\AnalyticsReporter;
use App\Services\WorkspaceContext;
use App\Services\WorkspaceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, WorkspaceContext $context, WorkspaceData $data, AnalyticsReporter $reporter): JsonResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace, 403);

        $filters = AnalyticsFilters::fromRequest($request);
        $accessibleLinkIds = $data->accessibleLinkIds($workspace, $request->user());

        return response()->json([
            'data' => $reporter->report($workspace, $filters, $accessibleLinkIds),
        ]);
    }
}

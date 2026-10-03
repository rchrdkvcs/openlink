<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Analytics\BuildAnalyticsReport;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, CurrentWorkspace $current, BuildAnalyticsReport $reporter): JsonResponse
    {
        return response()->json([
            'data' => $reporter->report($current->require(), AnalyticsFilters::fromRequest($request)),
        ]);
    }
}

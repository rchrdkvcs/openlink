<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireCurrent($request);

        return response()->json([
            'data' => $workspace->members()->with('user:id,name,email')->orderBy('role')->get(),
        ]);
    }
}

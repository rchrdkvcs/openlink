<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ShortLinks\ExportShortLinks;
use App\Actions\ShortLinks\ImportShortLinks;
use App\Actions\ShortLinks\LinksQuery;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Controllers\Controller;
use App\Http\Requests\ShortLinks\ImportShortLinksRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShortLinkCsvController extends Controller
{
    public function export(Request $request, CurrentWorkspace $current, ExportShortLinks $export): StreamedResponse
    {
        $workspace = $current->require();
        $filters = $request->validate(LinksQuery::exportRules());

        return $export->download($workspace, [...$filters, 'status' => $filters['status'] ?? LinksQuery::ALL_STATUSES]);
    }

    public function import(ImportShortLinksRequest $request, ImportShortLinks $import): JsonResponse
    {
        $arguments = [$request->user(), $request->workspace(), $request->csv(), $request->mapping(), $request->domainId()];

        return response()->json($request->boolean('dry_run') ? $import->preview(...$arguments) : $import->import(...$arguments));
    }
}

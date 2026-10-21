<?php

namespace App\Http\Controllers;

use App\Actions\ShortLinks\ExportShortLinks;
use App\Actions\ShortLinks\ImportShortLinks;
use App\Actions\ShortLinks\LinksQuery;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Requests\ShortLinks\ImportShortLinksRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShortLinkCsvController extends Controller
{
    public function export(Request $request, CurrentWorkspace $current, ExportShortLinks $export): StreamedResponse
    {
        $workspace = $current->require();

        return $export->download($workspace, $request->validate(LinksQuery::exportRules()));
    }

    public function example(ExportShortLinks $export): StreamedResponse
    {
        return $export->example();
    }

    public function preview(ImportShortLinksRequest $request, ImportShortLinks $import): RedirectResponse
    {
        Inertia::flash('linkImportPreview', $import->preview($request->user(), $request->workspace(), $request->csv(), $request->mapping(), $request->domainId()));

        return back();
    }

    public function store(ImportShortLinksRequest $request, ImportShortLinks $import): RedirectResponse
    {
        Inertia::flash('linkImportResult', $import->import($request->user(), $request->workspace(), $request->csv(), $request->mapping(), $request->domainId()));

        return back();
    }
}

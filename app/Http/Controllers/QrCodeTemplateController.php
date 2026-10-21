<?php

namespace App\Http\Controllers;

use App\Actions\QrCodes\QrCodeTemplates;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Models\QrCodeTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class QrCodeTemplateController extends Controller
{
    public function store(Request $request, CurrentWorkspace $current, QrCodeTemplates $templates): RedirectResponse
    {
        $workspace = $current->require('manage');

        $templates->create($workspace, $request->validate(QrCodeTemplates::rules($workspace)));

        return back();
    }

    public function update(Request $request, QrCodeTemplate $qrCodeTemplate, QrCodeTemplates $templates): RedirectResponse
    {
        Gate::authorize('update', $qrCodeTemplate);

        $templates->update(
            $qrCodeTemplate,
            $request->validate(QrCodeTemplates::rules($qrCodeTemplate->workspace, $qrCodeTemplate)),
        );

        return back();
    }

    public function destroy(QrCodeTemplate $qrCodeTemplate): RedirectResponse
    {
        Gate::authorize('delete', $qrCodeTemplate);

        if (! $qrCodeTemplate->delete()) {
            throw new RuntimeException('Unable to delete the QR Code template.');
        }

        return back();
    }
}

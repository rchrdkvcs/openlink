<?php

namespace App\Http\Controllers;

use App\Actions\Pages\WorkspaceShellPayload;
use App\Actions\QrCodes\CreateDirectQrCode;
use App\Actions\QrCodes\CreateQrCode;
use App\Actions\QrCodes\DeleteQrCode;
use App\Actions\QrCodes\QrCodeAppearance;
use App\Actions\QrCodes\QrCodePayload;
use App\Actions\QrCodes\UpdateQrCode;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Actions\Workspaces\WorkspacePayloads;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\QrCodes\QrCodeContent;
use App\Services\QrCodes\QrCodeRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, WorkspaceShellPayload $shell): \Inertia\Response
    {
        $workspace = $access->requireCurrent($request);
        $user = $request->user();

        $qrCodes = $workspace->qrCodes()
            ->whereNull('short_link_id')
            ->latest()
            ->get()
            ->map(fn (QrCode $qrCode) => QrCodePayload::make($qrCode));

        return Inertia::render('QrCodes/Index', [
            ...$shell->handle($workspace, $user),
            'qrCodes' => $qrCodes,
            'payloadTypes' => QrCodeContent::types(),
            'payloadDescriptors' => QrCodeContent::descriptors(),
        ]);
    }

    public function store(Request $request, ShortLink $shortLink, CreateQrCode $action): RedirectResponse
    {
        $qrCode = $action->handle($request, $shortLink, $request->validate(QrCodePayload::rules()));

        return redirect()->route('qr-codes.show', $qrCode);
    }

    public function storeDirect(Request $request, CreateDirectQrCode $action): RedirectResponse
    {
        $qrCode = $action->handle($request, $request->validate(QrCodePayload::directRules()));

        return redirect()->route('qr-codes.show', $qrCode);
    }

    public function show(Request $request, QrCode $qrCode, WorkspaceAccess $access, WorkspacePayloads $payloads, WorkspaceShellPayload $shell): \Inertia\Response
    {
        $workspace = $access->requireViewableQrCode($request, $qrCode);
        $user = $request->user();

        if ($qrCode->hasDirectPayload()) {
            return Inertia::render('QrCodes/DirectShow', [
                ...$shell->handle($workspace, $user),
                'qr' => QrCodePayload::make($qrCode),
                'payloadTypes' => QrCodeContent::types(),
                'payloadDescriptors' => QrCodeContent::descriptors(),
            ]);
        }

        return Inertia::render('QrCodes/Show', [
            ...$shell->handle($workspace, $user),
            'qr' => QrCodePayload::make($qrCode),
            'link' => $payloads->linkPayload($qrCode->shortLink),
        ]);
    }

    public function update(Request $request, QrCode $qrCode, UpdateQrCode $action): RedirectResponse
    {
        $rules = $qrCode->hasDirectPayload()
            ? QrCodePayload::directRules(creating: false)
            : QrCodePayload::rules(creating: false);

        $action->handle($request, $qrCode, $request->validate($rules));

        return back();
    }

    public function destroy(Request $request, QrCode $qrCode, DeleteQrCode $action): RedirectResponse
    {
        $action->handle($request, $qrCode);

        return redirect()->route($qrCode->hasDirectPayload() ? 'qr-codes.index' : 'links.index');
    }

    public function export(Request $request, QrCode $qrCode, string $format, WorkspaceAccess $access, QrCodeRenderer $renderer): Response
    {
        $access->requireViewableQrCode($request, $qrCode);
        abort_unless(in_array($format, ['png', 'svg'], true), 404);

        $size = $request->validate(['size' => ['nullable', 'integer', 'min:128', 'max:4096']])['size'] ?? null;

        $encodedContent = $qrCode->encodedContent();
        $contents = $format === 'png' ? $renderer->png($qrCode, $encodedContent, $size) : $renderer->svg($qrCode, $encodedContent, $size);
        $filename = (Str::slug($qrCode->name) ?: $qrCode->token).'.'.$format;

        return response($contents, 200, [
            'Content-Type' => $format === 'png' ? 'image/png' : 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function preview(Request $request, QrCode $qrCode, WorkspaceAccess $access, QrCodeRenderer $renderer, QrCodeAppearance $appearance): Response
    {
        $access->requireViewableQrCode($request, $qrCode);

        // Query overrides let the studio page live-preview unsaved settings.
        $rules = $qrCode->hasDirectPayload()
            ? QrCodePayload::directRules(creating: false)
            : QrCodePayload::rules(creating: false);

        $qrCode->fill($appearance->previewOverrides($request->validate($rules)));

        return response($renderer->svg($qrCode, $qrCode->encodedContent()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.$qrCode->token.'.svg"',
            'Cache-Control' => 'no-store',
        ]);
    }
}

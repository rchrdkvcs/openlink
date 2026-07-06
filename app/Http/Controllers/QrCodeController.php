<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\QrCodeRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends Controller
{
    public function store(Request $request, ShortLink $shortLink, WorkspaceAccess $access): RedirectResponse
    {
        $access->requireEditableShortLink($request, $shortLink);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'size' => ['nullable', 'integer', 'min:128', 'max:4096'],
            'foreground_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'margin' => ['nullable', 'integer', 'min:0', 'max:20'],
            'error_correction' => ['nullable', 'in:low,medium,quartile,high'],
        ]);

        $shortLink->qrCodes()->create([
            'name' => $data['name'],
            'token' => Str::random(32),
            'size' => $data['size'] ?? 1024,
            'foreground_color' => $data['foreground_color'] ?? '#111827',
            'background_color' => $data['background_color'] ?? '#ffffff',
            'margin' => $data['margin'] ?? 2,
            'error_correction' => $data['error_correction'] ?? 'medium',
        ]);

        return back();
    }

    public function export(Request $request, QrCode $qrCode, string $format, WorkspaceAccess $access, QrCodeRenderer $renderer): Response
    {
        $access->requireEditableQrCode($request, $qrCode);
        abort_unless(in_array($format, ['png', 'svg'], true), 404);

        $url = route('public.qr', ['qrCode' => $qrCode->token], true);
        $contents = $format === 'png' ? $renderer->png($qrCode, $url) : $renderer->svg($qrCode, $url);

        return response($contents, 200, [
            'Content-Type' => $format === 'png' ? 'image/png' : 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$qrCode->token.'.'.$format.'"',
        ]);
    }

    public function preview(Request $request, QrCode $qrCode, WorkspaceAccess $access, QrCodeRenderer $renderer): Response
    {
        $access->requireEditableQrCode($request, $qrCode);

        $url = route('public.qr', ['qrCode' => $qrCode->token], true);

        return response($renderer->svg($qrCode, $url), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.$qrCode->token.'.svg"',
        ]);
    }
}

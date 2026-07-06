<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\QrCodes\CreateQrCode;
use App\Actions\QrCodes\DeleteQrCode;
use App\Actions\QrCodes\QrCodePayload;
use App\Actions\QrCodes\UpdateQrCode;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Services\QrCodeRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends Controller
{
    public function store(Request $request, ShortLink $shortLink, CreateQrCode $action): JsonResponse
    {
        $qrCode = $action->handle($request, $shortLink, $request->validate(QrCodePayload::rules()));

        return response()->json(['data' => QrCodePayload::make($qrCode)], 201);
    }

    public function update(Request $request, QrCode $qrCode, UpdateQrCode $action): JsonResponse
    {
        $qrCode = $action->handle($request, $qrCode, $request->validate(QrCodePayload::rules(creating: false)));

        return response()->json(['data' => QrCodePayload::make($qrCode)]);
    }

    public function destroy(Request $request, QrCode $qrCode, DeleteQrCode $action): JsonResponse
    {
        $action->handle($request, $qrCode);

        return response()->json(['message' => 'QR code deleted.']);
    }

    public function export(Request $request, QrCode $qrCode, string $format, WorkspaceAccess $access, QrCodeRenderer $renderer): Response
    {
        $access->requireEditableQrCode($request, $qrCode);
        abort_unless(in_array($format, ['png', 'svg'], true), 404);

        $size = $request->validate(['size' => ['nullable', 'integer', 'min:128', 'max:4096']])['size'] ?? null;

        $url = $qrCode->publicUrl();
        $contents = $format === 'png' ? $renderer->png($qrCode, $url, $size) : $renderer->svg($qrCode, $url, $size);
        $filename = (Str::slug($qrCode->name) ?: $qrCode->token).'.'.$format;

        return response($contents, 200, [
            'Content-Type' => $format === 'png' ? 'image/png' : 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function preview(Request $request, QrCode $qrCode, WorkspaceAccess $access, QrCodeRenderer $renderer): Response
    {
        $access->requireEditableQrCode($request, $qrCode);

        $overrides = collect($request->validate(QrCodePayload::rules(creating: false)))
            ->except(['name', 'logo', 'remove_logo'])
            ->filter(fn ($value) => $value !== null);

        $qrCode->fill($overrides->all());

        return response($renderer->svg($qrCode, $qrCode->publicUrl()), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="'.$qrCode->token.'.svg"',
            'Cache-Control' => 'no-store',
        ]);
    }
}

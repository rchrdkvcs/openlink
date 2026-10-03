<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\QrCodes\CreateQrCode;
use App\Actions\QrCodes\DeleteQrCode;
use App\Actions\QrCodes\QrCodeImages;
use App\Actions\QrCodes\QrCodePayload;
use App\Actions\QrCodes\UpdateQrCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\QrCodes\ExportQrCodeRequest;
use App\Http\Requests\QrCodes\PreviewQrCodeRequest;
use App\Http\Requests\QrCodes\StoreQrCodeRequest;
use App\Http\Requests\QrCodes\UpdateQrCodeRequest;
use App\Models\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends Controller
{
    public function store(StoreQrCodeRequest $request, CreateQrCode $action): JsonResponse
    {
        $qrCode = $action->handle($request, $request->validated());

        return response()->json(['data' => QrCodePayload::make($qrCode)], 201);
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode, UpdateQrCode $action): JsonResponse
    {
        $qrCode = $action->handle($request, $qrCode, $request->validated());

        return response()->json(['data' => QrCodePayload::make($qrCode)]);
    }

    public function destroy(Request $request, QrCode $qrCode, DeleteQrCode $action): JsonResponse
    {
        $action->handle($request, $qrCode);

        return response()->json(['message' => 'QR code deleted.']);
    }

    public function export(ExportQrCodeRequest $request, QrCode $qrCode, string $format, QrCodeImages $images): Response
    {
        return $images->export($qrCode, $format, $request->size());
    }

    public function preview(PreviewQrCodeRequest $request, QrCode $qrCode, QrCodeImages $images): Response
    {
        return $images->preview($qrCode, $request->validated());
    }
}

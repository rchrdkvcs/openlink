<?php

namespace App\Http\Controllers;

use App\Actions\QrCodes\CreateQrCode;
use App\Actions\QrCodes\DeleteQrCode;
use App\Actions\QrCodes\QrCodeImages;
use App\Actions\QrCodes\QrCodePayload;
use App\Actions\QrCodes\ShortLinkOptions;
use App\Actions\QrCodes\UpdateQrCode;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Requests\QrCodes\ExportQrCodeRequest;
use App\Http\Requests\QrCodes\PreviewQrCodeRequest;
use App\Http\Requests\QrCodes\StoreQrCodeRequest;
use App\Http\Requests\QrCodes\UpdateQrCodeRequest;
use App\Models\QrCode;
use App\Services\QrCodes\QrCodeContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class QrCodeController extends Controller
{
    public function index(Request $request, CurrentWorkspace $current, ShortLinkOptions $options): \Inertia\Response
    {
        $workspace = $current->require();
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($filters['search'] ?? '');

        $qrCodes = $workspace->qrCodes()
            ->with('shortLink.domain')
            ->withScanCount()
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.$search.'%']))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(24, ['*'], 'page', $filters['page'] ?? 1)
            ->through(fn (QrCode $qrCode) => QrCodePayload::make($qrCode));

        return Inertia::render('QrCodes/Index', [
            'qrCodes' => $qrCodes->items(),
            'qrPagination' => [
                'currentPage' => $qrCodes->currentPage(),
                'lastPage' => $qrCodes->lastPage(),
                'total' => $qrCodes->total(),
            ],
            'qrFilters' => ['search' => $filters['search'] ?? ''],
            'payloadTypes' => QrCodeContent::types(),
            'payloadDescriptors' => QrCodeContent::descriptors(),
            'shortLinks' => $options->for($workspace),
        ]);
    }

    public function store(StoreQrCodeRequest $request, CreateQrCode $action): RedirectResponse
    {
        $qrCode = $action->handle($request, $request->validated());

        return redirect()->route('qr-codes.show', $qrCode);
    }

    public function show(Request $request, QrCode $qrCode, CurrentWorkspace $current, ShortLinkOptions $options): \Inertia\Response
    {
        $workspace = $current->require();
        Gate::authorize('view', $qrCode);
        abort_unless($qrCode->workspace_id === $workspace->id, 403);

        return Inertia::render('QrCodes/Show', [
            'qr' => QrCodePayload::make($qrCode),
            'payloadTypes' => QrCodeContent::types(),
            'payloadDescriptors' => QrCodeContent::descriptors(),
            'shortLinks' => $options->for($workspace, selectedId: $qrCode->short_link_id),
        ]);
    }

    public function shortLinks(Request $request, CurrentWorkspace $current, ShortLinkOptions $options): JsonResponse
    {
        $workspace = $current->require();
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200']]);

        return response()->json(['data' => $options->for($workspace, $filters['search'] ?? '')]);
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode, UpdateQrCode $action): RedirectResponse
    {
        $action->handle($request, $qrCode, $request->validated());

        return back();
    }

    public function destroy(Request $request, QrCode $qrCode, DeleteQrCode $action): RedirectResponse
    {
        $action->handle($request, $qrCode);

        return redirect()->route('qr-codes.index');
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

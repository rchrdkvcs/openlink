<?php

namespace App\Http\Controllers;

use App\Actions\Pages\WorkspaceShellPayload;
use App\Actions\QrCodes\CreateQrCode;
use App\Actions\QrCodes\DeleteQrCode;
use App\Actions\QrCodes\QrCodeAppearance;
use App\Actions\QrCodes\QrCodePayload;
use App\Actions\QrCodes\UpdateQrCode;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\QrCode;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\QrCodes\QrCodeContent;
use App\Services\QrCodes\QrCodeRenderer;
use Illuminate\Http\JsonResponse;
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
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $qrCodes = $workspace->qrCodes()
            ->with('shortLink.domain')
            ->withCount(['analyticsEvents as scans_count' => fn ($events) => $events->successful()->where('metric', 'scan')])
            ->when(trim($filters['search'] ?? '') !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.trim($filters['search']).'%']))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(24, ['*'], 'page', $filters['page'] ?? 1)
            ->through(fn (QrCode $qrCode) => QrCodePayload::make($qrCode));

        return Inertia::render('QrCodes/Index', [
            ...$shell->handle($workspace, $user),
            'qrCodes' => $qrCodes->items(),
            'qrPagination' => [
                'currentPage' => $qrCodes->currentPage(),
                'lastPage' => $qrCodes->lastPage(),
                'total' => $qrCodes->total(),
            ],
            'qrFilters' => ['search' => $filters['search'] ?? ''],
            'payloadTypes' => QrCodeContent::types(),
            'payloadDescriptors' => QrCodeContent::descriptors(),
            'shortLinks' => $this->shortLinkOptions($workspace),
        ]);
    }

    public function store(Request $request, CreateQrCode $action): RedirectResponse
    {
        $qrCode = $action->handle($request, $request->validate(QrCodePayload::unifiedRules()));

        return redirect()->route('qr-codes.show', $qrCode);
    }

    public function show(Request $request, QrCode $qrCode, WorkspaceAccess $access, WorkspaceShellPayload $shell): \Inertia\Response
    {
        $workspace = $access->requireViewableQrCode($request, $qrCode);
        $user = $request->user();

        return Inertia::render('QrCodes/Show', [
            ...$shell->handle($workspace, $user),
            'qr' => QrCodePayload::make($qrCode),
            'payloadTypes' => QrCodeContent::types(),
            'payloadDescriptors' => QrCodeContent::descriptors(),
            'shortLinks' => $this->shortLinkOptions($workspace, selectedId: $qrCode->short_link_id),
        ]);
    }

    public function shortLinks(Request $request, WorkspaceAccess $access): JsonResponse
    {
        $workspace = $access->requireCurrent($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200']]);

        return response()->json(['data' => $this->shortLinkOptions($workspace, $filters['search'] ?? '')]);
    }

    private function shortLinkOptions(Workspace $workspace, string $search = '', ?int $selectedId = null): array
    {
        $query = $workspace->shortLinks()->with('domain')->primary();
        $search = trim($search);
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->whereRaw('LOWER(slug) LIKE LOWER(?)', [$term])
                    ->orWhereRaw('LOWER(destination_url) LIKE LOWER(?)', [$term])
                    ->orWhereHas('domain', fn ($domain) => $domain
                        ->whereRaw("LOWER('https://' || domains.hostname || '/' || short_links.slug) LIKE LOWER(?)", [$term]));
            });
        }

        $links = $query->orderByDesc('created_at')->orderByDesc('id')->limit(50)->get();
        if ($selectedId && ! $links->contains('id', $selectedId)) {
            $selected = $workspace->shortLinks()->with('domain')->whereKey($selectedId)->first();
            if ($selected) {
                $links->prepend($selected);
            }
        }

        return $links->map(fn (ShortLink $link) => [
            'id' => $link->id,
            'short_url' => 'https://'.$link->domain->hostname.'/'.$link->slug,
            'destination_url' => $link->destination_url,
        ])->all();
    }

    public function update(Request $request, QrCode $qrCode, UpdateQrCode $action): RedirectResponse
    {
        $action->handle($request, $qrCode, $request->validate(QrCodePayload::unifiedRules(creating: false)));

        return back();
    }

    public function destroy(Request $request, QrCode $qrCode, DeleteQrCode $action): RedirectResponse
    {
        $action->handle($request, $qrCode);

        return redirect()->route('qr-codes.index');
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

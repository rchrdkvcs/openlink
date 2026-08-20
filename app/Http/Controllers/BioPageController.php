<?php

namespace App\Http\Controllers;

use App\Actions\BioPages\BioPageMutation;
use App\Actions\BioPages\BioPagePayload;
use App\Actions\QrCodes\QrCodePayload;
use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\BioPage;
use App\Models\Domain;
use App\Models\ShortLink;
use App\Services\BioPages\BioPagePresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BioPageController extends Controller
{
    public function index(Request $request, WorkspaceAccess $access, BioPagePayload $payload): Response
    {
        $workspace = $access->requireCurrent($request);
        $bioPages = BioPage::query()
            ->with(['draftDomain', 'publishedDomain', 'elements'])
            ->where('workspace_id', $workspace->id)
            ->latest('updated_at')
            ->get()
            ->map(fn (BioPage $bioPage) => $payload->summary($bioPage));

        return Inertia::render('BioPages/Index', [
            'bioPages' => $bioPages,
            'canCreate' => $access->canEditWorkspace($request->user(), $workspace),
        ]);
    }

    public function show(Request $request, BioPage $bioPage, WorkspaceAccess $access, BioPagePayload $payload, BioPagePresence $presence): Response
    {
        $workspace = $access->requireCurrent($request);
        abort_unless($bioPage->workspace_id === $workspace->id && $request->user()->can('view', $bioPage), 403);

        $domains = Domain::query()
            ->where(fn ($query) => $query->where('workspace_id', $workspace->id)->orWhere('is_default', true))
            ->orderByDesc('is_default')
            ->orderBy('hostname')
            ->get()
            ->map(fn (Domain $domain) => $payload->domain($domain));

        $shortLinks = ShortLink::query()
            ->with(['domain', 'folder.workspace'])
            ->where('workspace_id', $workspace->id)
            ->get()
            ->filter(fn (ShortLink $shortLink) => $access->canViewShortLink($request->user(), $shortLink))
            ->map(fn (ShortLink $shortLink) => $payload->shortLink($shortLink))
            ->values();

        $qrCodes = $bioPage->qrCodes()
            ->withCount([
                'analyticsEvents as scans_count' => fn ($events) => $events->successful()->where('metric', 'scan'),
            ])
            ->latest()
            ->get()
            ->map(fn ($qrCode) => QrCodePayload::make($qrCode));

        $canEdit = $request->user()->can('update', $bioPage);

        return Inertia::render('BioPages/Edit', [
            'bioPage' => $payload->editor($bioPage),
            'domains' => $domains,
            'shortLinks' => $shortLinks,
            'qrCodes' => $qrCodes,
            'canEdit' => $canEdit,
            'canPublish' => $request->user()->can('publish', $bioPage),
            'canDelete' => $request->user()->can('delete', $bioPage),
            'activeEditors' => $canEdit
                ? $presence->touch($bioPage, $request->user())
                : [],
        ]);
    }

    public function presence(Request $request, BioPage $bioPage, WorkspaceAccess $access, BioPagePresence $presence): JsonResponse
    {
        $workspace = $access->requireCurrent($request);
        abort_unless($bioPage->workspace_id === $workspace->id && $request->user()->can('update', $bioPage), 403);

        return response()->json([
            'activeEditors' => $presence->touch($bioPage, $request->user()),
        ]);
    }

    public function store(Request $request, BioPageMutation $mutation): RedirectResponse
    {
        $bioPage = $mutation->create($request);

        return redirect()->route('bio-pages.show', $bioPage);
    }

    public function update(Request $request, BioPage $bioPage, BioPageMutation $mutation, BioPagePresence $presence): RedirectResponse
    {
        $mutation->update($request, $bioPage);
        $presence->touch($bioPage, $request->user());

        return back();
    }

    public function publish(Request $request, BioPage $bioPage, BioPageMutation $mutation): RedirectResponse
    {
        $mutation->publish($request, $bioPage);

        return back();
    }

    public function unpublish(Request $request, BioPage $bioPage, BioPageMutation $mutation): RedirectResponse
    {
        $mutation->unpublish($request, $bioPage);

        return back();
    }

    public function destroy(Request $request, BioPage $bioPage, BioPageMutation $mutation): RedirectResponse
    {
        $mutation->delete($request, $bioPage);

        return redirect()->route('bio-pages.index');
    }

    public function storeMedia(Request $request, BioPage $bioPage, BioPageMutation $mutation): RedirectResponse
    {
        $mutation->storeMedia($request, $bioPage);

        return back();
    }

    public function destroyMedia(Request $request, BioPage $bioPage, string $type, BioPageMutation $mutation): RedirectResponse
    {
        $mutation->deleteMedia($request, $bioPage, $type);

        return back();
    }
}

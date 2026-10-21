<?php

namespace App\Http\Controllers;

use App\Actions\LinkPages\CreateLinkPage;
use App\Actions\LinkPages\DeleteLinkPage;
use App\Actions\LinkPages\LinkPagePayload;
use App\Actions\LinkPages\PublishLinkPage;
use App\Actions\LinkPages\UpdateLinkPage;
use App\Actions\QrCodes\ShortLinkOptions;
use App\Actions\Workspaces\CurrentWorkspace;
use App\Http\Requests\LinkPages\StoreLinkPageRequest;
use App\Http\Requests\LinkPages\UpdateLinkPageRequest;
use App\Models\LinkPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LinkPageController extends Controller
{
    public function __construct(private readonly LinkPagePayload $payload) {}

    public function index(CurrentWorkspace $current): Response
    {
        $workspace = $current->require();

        return Inertia::render('LinkPages/Index', [
            'linkPages' => $workspace->linkPages()
                ->withCount('items')
                ->orderByDesc('created_at')->orderByDesc('id')
                ->get()
                ->map(fn (LinkPage $linkPage) => $this->payload->summary($linkPage))
                ->all(),
            'pathPrefix' => $this->pathPrefix(),
        ]);
    }

    public function store(StoreLinkPageRequest $request, CreateLinkPage $action): RedirectResponse
    {
        return redirect()->route('link-pages.show', $action->handle($request->validated()));
    }

    public function show(LinkPage $linkPage, CurrentWorkspace $current, ShortLinkOptions $options): Response
    {
        $workspace = $current->require();
        Gate::authorize('view', $linkPage);

        return Inertia::render('LinkPages/Show', [
            'linkPage' => $this->payload->editor($linkPage),
            'shortLinks' => $options->for($workspace),
            'pathPrefix' => $this->pathPrefix(),
        ]);
    }

    public function shortLinks(Request $request, CurrentWorkspace $current, ShortLinkOptions $options): JsonResponse
    {
        $workspace = $current->require();
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200']]);

        return response()->json(['data' => $options->for($workspace, $filters['search'] ?? '')]);
    }

    public function update(UpdateLinkPageRequest $request, LinkPage $linkPage, UpdateLinkPage $action): RedirectResponse
    {
        $action->handle($request, $linkPage, $request->validated());

        return back();
    }

    public function publish(Request $request, LinkPage $linkPage, PublishLinkPage $action): RedirectResponse
    {
        $action->publish($request, $linkPage);

        return back();
    }

    public function unpublish(Request $request, LinkPage $linkPage, PublishLinkPage $action): RedirectResponse
    {
        $action->unpublish($request, $linkPage);

        return back();
    }

    public function destroy(Request $request, LinkPage $linkPage, DeleteLinkPage $action): RedirectResponse
    {
        $action->handle($request, $linkPage);

        return redirect()->route('link-pages.index');
    }

    private function pathPrefix(): string
    {
        return rtrim(url('/'.LinkPage::PATH_PREFIX), '/').'/';
    }
}

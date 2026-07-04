<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Services\SlugService;
use App\Services\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ShortLinkController extends Controller
{
    public function store(Request $request, WorkspaceContext $context, SlugService $slugs): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $context->canEditWorkspace($request->user(), $workspace), 403);

        $data = $request->validate([
            'domain_id' => ['required', 'integer'],
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'slug' => ['nullable', 'string', 'max:512'],
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => ['nullable', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $domain = $this->domainForWorkspace($data['domain_id'], $workspace->id);
        abort_unless($domain, 422);
        abort_unless($domain->isUsable(), 422);
        $folder = filled($data['folder_id'] ?? null) ? Folder::query()->find($data['folder_id']) : null;
        abort_if($folder && ! $context->canEditFolder($request->user(), $folder), 403);

        $slug = filled($data['slug'] ?? null)
            ? $slugs->validateCustom($domain, $data['slug'])
            : $slugs->generate($domain);

        $this->assertNoLoop($request, $domain, $slug, $data['destination_url']);

        $shortLink = ShortLink::create([
            'workspace_id' => $workspace->id,
            'folder_id' => $folder?->id,
            'domain_id' => $domain->id,
            'slug' => $slug,
            'destination_url' => $data['destination_url'],
            'fallback_url' => $data['fallback_url'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? true,
            'activates_at' => $data['activates_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'visit_limit' => $data['visit_limit'] ?? null,
            'password_hash' => filled($data['password'] ?? null) ? Hash::make($data['password']) : null,
        ]);

        $this->syncTags($shortLink, $data['tags'] ?? '');

        return back();
    }

    public function update(Request $request, ShortLink $shortLink, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canEditShortLink($request->user(), $shortLink), 403);

        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => ['required', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'password' => ['nullable', 'string', 'min:4', 'max:255'],
        ]);

        $this->assertNoLoop($request, $shortLink->domain, $shortLink->slug, $data['destination_url']);
        $folder = filled($data['folder_id'] ?? null) ? Folder::query()->find($data['folder_id']) : null;
        abort_if($folder && ! $context->canEditFolder($request->user(), $folder), 403);

        $shortLink->fill([
            'folder_id' => $folder?->id,
            'destination_url' => $data['destination_url'],
            'fallback_url' => $data['fallback_url'] ?? null,
            'is_enabled' => $data['is_enabled'],
            'activates_at' => $data['activates_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'visit_limit' => $data['visit_limit'] ?? null,
        ]);

        if (filled($data['password'] ?? null)) {
            $shortLink->password_hash = Hash::make($data['password']);
        }

        $shortLink->save();

        return back();
    }

    public function archive(Request $request, ShortLink $shortLink, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canEditShortLink($request->user(), $shortLink), 403);

        $shortLink->update(['archived_at' => now()]);

        return back();
    }

    public function move(Request $request, ShortLink $shortLink, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        $shortLink->loadMissing('workspace', 'folder.workspace');
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canEditShortLink($request->user(), $shortLink), 403);

        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $folder = filled($data['folder_id'] ?? null) ? Folder::query()->find($data['folder_id']) : null;
        abort_if($folder && ! $context->canEditFolder($request->user(), $folder), 403);

        $shortLink->update(['folder_id' => $folder?->id]);

        return back();
    }

    public function destroy(Request $request, ShortLink $shortLink, WorkspaceContext $context): \Illuminate\Http\RedirectResponse
    {
        $workspace = $context->current($request);
        abort_unless($workspace && $shortLink->workspace_id === $workspace->id && $context->canManageWorkspace($request->user(), $workspace), 403);

        $shortLink->delete();

        return back();
    }

    private function domainForWorkspace(int $domainId, int $workspaceId): ?Domain
    {
        return Domain::query()
            ->whereKey($domainId)
            ->where(fn ($query) => $query->where('workspace_id', $workspaceId)->orWhere('is_default', true))
            ->first();
    }

    private function assertNoLoop(Request $request, Domain $domain, string $slug, string $destinationUrl): void
    {
        $targetHost = parse_url($destinationUrl, PHP_URL_HOST);
        $targetPath = trim((string) parse_url($destinationUrl, PHP_URL_PATH), '/');

        if ($targetHost === $domain->hostname && $targetPath === trim($slug, '/')) {
            abort(422, 'Destination URL cannot point to the same short URL.');
        }
    }

    private function syncTags(ShortLink $shortLink, string $tags): void
    {
        $tagIds = collect(explode(',', $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->map(function (string $name) use ($shortLink) {
                return Tag::query()->firstOrCreate([
                    'workspace_id' => $shortLink->workspace_id,
                    'name' => $name,
                ])->id;
            });

        if ($tagIds->isNotEmpty()) {
            $shortLink->tags()->sync($tagIds->all());
        }
    }
}

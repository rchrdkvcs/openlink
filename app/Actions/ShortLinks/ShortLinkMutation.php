<?php

namespace App\Actions\ShortLinks;

use App\Actions\Workspaces\WorkspaceAccess;
use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Models\Workspace;
use App\Services\ShortLinks\ShortUrlComposition;
use App\Services\ShortLinks\SmartRouting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ShortLinkMutation
{
    public function __construct(
        private readonly WorkspaceAccess $access,
        private readonly ShortUrlComposition $shortUrls,
        private readonly SmartRouting $routing,
    ) {}

    public function create(Request $request): ShortLink
    {
        $workspace = $this->access->requireEditableWorkspace($request);
        $data = $request->validate($this->rules($workspace, creating: true));
        $fallbackDomain = $workspace->preferredDomain ?? $this->defaultDomain();

        abort_unless(($data['domain_id'] ?? null) || $fallbackDomain, 422, 'No domain available for this workspace.');

        return $this->createFromData($workspace, $request, $data, $fallbackDomain);
    }

    public function update(Request $request, ShortLink $shortLink): ShortLink
    {
        $workspace = $this->access->requireEditableShortLink($request, $shortLink);
        $data = $request->validate($this->rules($workspace, creating: false));

        return $this->updateFromData($workspace, $request, $shortLink, $data);
    }

    public function move(Request $request, ShortLink $shortLink): ShortLink
    {
        $workspace = $this->access->requireEditableShortLink($request, $shortLink);
        $data = $request->validate([
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
        ]);

        $folder = $this->folderForWorkspace($workspace, $request->user(), $data['folder_id'] ?? null);
        $shortLink->update(['folder_id' => $folder?->id]);

        return $shortLink;
    }

    public function archive(Request $request, ShortLink $shortLink): ShortLink
    {
        $this->access->requireEditableShortLink($request, $shortLink);
        $shortLink->update(['archived_at' => now()]);

        return $shortLink;
    }

    public function delete(Request $request, ShortLink $shortLink): void
    {
        $this->access->requireEditableShortLink($request, $shortLink);
        $shortLink->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createFromData(Workspace $workspace, Request $request, array $data, ?Domain $fallbackDomain): ShortLink
    {
        $domain = $this->shortUrls->requireUsableDomain($data['domain_id'] ?? null, $workspace, $fallbackDomain);
        $folder = $this->folderForWorkspace($workspace, $request->user(), $data['folder_id'] ?? null);
        $slug = $this->shortUrls->slugForCreate($domain, $data);

        $this->shortUrls->assertNoLoop($domain, $slug, $data['destination_url']);

        return DB::transaction(function () use ($workspace, $folder, $domain, $slug, $data): ShortLink {
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
            $this->routing->sync($shortLink, $data['routing_rules'] ?? []);

            return $shortLink;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateFromData(Workspace $workspace, Request $request, ShortLink $shortLink, array $data): ShortLink
    {
        $folder = $this->folderForWorkspace($workspace, $request->user(), $data['folder_id'] ?? null);
        $shortLink->loadMissing('domain');
        $domain = $shortLink->domain;

        if (isset($data['domain_id']) && (int) $data['domain_id'] !== $shortLink->domain_id) {
            $domain = $this->shortUrls->requireUsableDomain($data['domain_id'], $workspace);
        }

        $slug = $this->shortUrls->slugForUpdate($shortLink, $domain, $data);
        $this->shortUrls->assertNoLoop($domain, $slug, $data['destination_url']);

        return DB::transaction(function () use ($shortLink, $folder, $domain, $slug, $data): ShortLink {
            $shortLink->fill([
                'folder_id' => $folder?->id,
                'domain_id' => $domain->id,
                'slug' => $slug,
                'destination_url' => $data['destination_url'],
                'fallback_url' => $data['fallback_url'] ?? null,
                'is_enabled' => $data['is_enabled'],
                'activates_at' => $data['activates_at'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'visit_limit' => $data['visit_limit'] ?? null,
            ]);

            if (array_key_exists('password', $data)) {
                $shortLink->password_hash = filled($data['password'] ?? null)
                    ? Hash::make($data['password'])
                    : null;
            }

            $shortLink->save();

            if (array_key_exists('routing_rules', $data)) {
                $this->routing->sync($shortLink, $data['routing_rules'] ?? []);
            }

            return $shortLink;
        });
    }

    /** @return array<string, list<mixed>> */
    private function rules(Workspace $workspace, bool $creating): array
    {
        return [
            'domain_id' => [$creating ? 'nullable' : 'sometimes', $creating ? 'integer' : 'required', 'integer'],
            'folder_id' => ['nullable', Rule::exists('folders', 'id')->where('workspace_id', $workspace->id)],
            'slug' => [$creating ? 'nullable' : 'sometimes', $creating ? 'string' : 'required', 'string', 'max:512'],
            'destination_url' => ['required', 'url:http,https'],
            'fallback_url' => ['nullable', 'url:http,https'],
            'is_enabled' => [$creating ? 'nullable' : 'required', 'boolean'],
            'activates_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'visit_limit' => ['nullable', 'integer', 'min:1'],
            'password' => ['sometimes', 'nullable', 'string', 'min:4', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
            'routing_rules' => ['nullable', 'array'],
        ];
    }

    private function folderForWorkspace(Workspace $workspace, mixed $user, mixed $folderId): ?Folder
    {
        if (! filled($folderId)) {
            return null;
        }

        $folder = Folder::query()
            ->whereKey((int) $folderId)
            ->where('workspace_id', $workspace->id)
            ->firstOrFail();

        abort_unless($this->access->canEditFolder($user, $folder), 403);

        return $folder;
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

    private function defaultDomain(): ?Domain
    {
        return Domain::query()->where('is_default', true)->first();
    }
}

<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Folder;
use App\Models\ShortLink;
use App\Models\Tag;
use App\Models\Workspace;
use Illuminate\Support\Facades\Hash;

class ShortLinkManager
{
    public function __construct(
        private readonly SlugService $slugs,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated short link attributes.
     */
    public function create(Workspace $workspace, Domain $domain, ?Folder $folder, array $data): ShortLink
    {
        $slug = filled($data['slug'] ?? null)
            ? $this->slugs->validateCustom($domain, $data['slug'])
            : $this->slugs->generate($domain);

        $this->assertNoLoop($domain, $slug, $data['destination_url']);

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

        return $shortLink;
    }

    /**
     * @param  array<string, mixed>  $data  Validated short link attributes.
     */
    public function update(ShortLink $shortLink, ?Folder $folder, array $data, bool $updatePassword): ShortLink
    {
        $this->assertNoLoop($shortLink->domain, $shortLink->slug, $data['destination_url']);

        $shortLink->fill([
            'folder_id' => $folder?->id,
            'destination_url' => $data['destination_url'],
            'fallback_url' => $data['fallback_url'] ?? null,
            'is_enabled' => $data['is_enabled'],
            'activates_at' => $data['activates_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'visit_limit' => $data['visit_limit'] ?? null,
        ]);

        if ($updatePassword) {
            $shortLink->password_hash = filled($data['password'] ?? null)
                ? Hash::make($data['password'])
                : null;
        }

        $shortLink->save();

        return $shortLink;
    }

    public function domainForWorkspace(int $domainId, int $workspaceId): ?Domain
    {
        return Domain::query()
            ->whereKey($domainId)
            ->where(fn ($query) => $query->where('workspace_id', $workspaceId)->orWhere('is_default', true))
            ->first();
    }

    public function assertNoLoop(Domain $domain, string $slug, string $destinationUrl): void
    {
        $targetHost = parse_url($destinationUrl, PHP_URL_HOST);
        $targetPath = trim((string) parse_url($destinationUrl, PHP_URL_PATH), '/');

        if ($targetHost === $domain->hostname && $targetPath === trim($slug, '/')) {
            abort(422, 'Destination URL cannot point to the same short URL.');
        }
    }

    public function syncTags(ShortLink $shortLink, string $tags): void
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

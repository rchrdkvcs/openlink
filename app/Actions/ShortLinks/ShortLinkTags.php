<?php

namespace App\Actions\ShortLinks;

use App\Models\ShortLink;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShortLinkTags
{
    private const PIVOT = 'short_link_tag';

    public function replace(ShortLink $shortLink, ?string $tags): void
    {
        $shortLink->tags()->sync($this->idsFor($shortLink->workspace_id, explode(',', (string) $tags)));
    }

    public function addToEach(Collection $shortLinks, int $workspaceId, array $names): void
    {
        $tagIds = $this->idsFor($workspaceId, $names);
        $now = now();

        $rows = $shortLinks->flatMap(fn (ShortLink $shortLink) => array_map(fn (int $tagId) => [
            'short_link_id' => $shortLink->id,
            'tag_id' => $tagId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $tagIds));

        if ($rows->isNotEmpty()) {
            DB::table(self::PIVOT)->insertOrIgnore($rows->all());
        }
    }

    public function removeFromEach(Collection $shortLinks, int $workspaceId, array $names): void
    {
        $tagIds = Tag::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('name', $this->names($names))
            ->pluck('id');

        if ($tagIds->isEmpty() || $shortLinks->isEmpty()) {
            return;
        }

        DB::table(self::PIVOT)
            ->whereIn('short_link_id', $shortLinks->modelKeys())
            ->whereIn('tag_id', $tagIds->all())
            ->delete();
    }

    private function idsFor(int $workspaceId, array $names): array
    {
        $names = $this->names($names);

        if ($names === []) {
            return [];
        }

        $now = now();
        Tag::query()->insertOrIgnore(array_map(fn (string $name) => [
            'workspace_id' => $workspaceId,
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ], $names));

        return Tag::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('name', $names)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function names(array $names): array
    {
        return collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

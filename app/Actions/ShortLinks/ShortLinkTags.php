<?php

namespace App\Actions\ShortLinks;

use App\Models\ShortLink;
use App\Models\Tag;

class ShortLinkTags
{
    public function replace(ShortLink $shortLink, ?string $tags): void
    {
        $shortLink->tags()->sync($this->idsFor($shortLink, explode(',', (string) $tags)));
    }

    public function add(ShortLink $shortLink, array $names): void
    {
        $shortLink->tags()->syncWithoutDetaching($this->idsFor($shortLink, $names));
    }

    public function remove(ShortLink $shortLink, array $names): void
    {
        $tagIds = Tag::query()
            ->where('workspace_id', $shortLink->workspace_id)
            ->whereIn('name', $this->names($names))
            ->pluck('id');

        $shortLink->tags()->detach($tagIds->all());
    }

    private function idsFor(ShortLink $shortLink, array $names): array
    {
        return array_map(fn (string $name) => Tag::query()->firstOrCreate([
            'workspace_id' => $shortLink->workspace_id,
            'name' => $name,
        ])->id, $this->names($names));
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

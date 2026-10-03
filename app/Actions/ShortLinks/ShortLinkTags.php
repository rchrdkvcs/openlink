<?php

namespace App\Actions\ShortLinks;

use App\Models\ShortLink;
use App\Models\Tag;

class ShortLinkTags
{
    public function replace(ShortLink $shortLink, ?string $tags): void
    {
        $tagIds = collect(explode(',', (string) $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Tag::query()->firstOrCreate([
                'workspace_id' => $shortLink->workspace_id,
                'name' => $name,
            ])->id);

        $shortLink->tags()->sync($tagIds->all());
    }
}

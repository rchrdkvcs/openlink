<?php

namespace App\Actions\LinkPages;

use App\Models\LinkPage;
use App\Services\LinkPages\LinkPageEntries;
use App\Services\LinkPages\LinkPageLogoStorage;

class LinkPagePayload
{
    public function __construct(
        private readonly LinkPageEntries $entries,
        private readonly LinkPageLogoStorage $logos,
    ) {}

    public function summary(LinkPage $linkPage): array
    {
        return [
            ...$linkPage->only(['id', 'name', 'path', 'title']),
            'is_published' => $linkPage->isPublished(),
            'public_url' => $linkPage->publicUrl(),
            'items_count' => (int) ($linkPage->items_count ?? $linkPage->items()->count()),
            'updated_at' => $linkPage->updated_at,
        ];
    }

    public function editor(LinkPage $linkPage): array
    {
        return [
            ...$this->summary($linkPage),
            'description' => $linkPage->description,
            'has_logo' => $linkPage->hasLogo(),
            'logo' => $this->logos->dataUri($linkPage),
            'published_at' => $linkPage->published_at,
            'items' => $this->entries->forEditor($linkPage),
        ];
    }

    public function visitor(LinkPage $linkPage): array
    {
        return [
            'title' => $linkPage->title,
            'description' => $linkPage->description,
            'logo' => $this->logos->dataUri($linkPage),
            'links' => $this->entries->forVisitors($linkPage),
        ];
    }
}

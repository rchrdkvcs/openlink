<?php

namespace App\Services\Webhooks;

use App\Models\ShortLink;
use App\Services\ShortLinks\ShortLinkLifecycle;

class ShortLinkEventData
{
    public function __construct(private readonly ShortLinkLifecycle $lifecycle) {}

    public function handle(ShortLink $link): array
    {
        $link->load(['domain', 'folder', 'tags']);

        return [
            'id' => $link->id,
            'slug' => $link->slug,
            'short_url' => $link->shortUrl(),
            'destination_url' => $link->destination_url,
            'fallback_url' => $link->fallback_url,
            'status' => $this->lifecycle->status($link),
            'is_enabled' => $link->is_enabled,
            'has_password' => $link->hasPassword(),
            'domain' => ['id' => $link->domain->id, 'hostname' => $link->domain->hostname],
            'folder' => $link->folder ? ['id' => $link->folder->id, 'name' => $link->folder->name] : null,
            'tags' => $link->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values()->all(),
            'activates_at' => $link->activates_at?->toIso8601ZuluString(),
            'expires_at' => $link->expires_at?->toIso8601ZuluString(),
            'visit_limit' => $link->visit_limit,
            'archived_at' => $link->archived_at?->toIso8601ZuluString(),
            'created_at' => $link->created_at?->toIso8601ZuluString(),
            'updated_at' => $link->updated_at?->toIso8601ZuluString(),
        ];
    }
}

<?php

namespace App\Actions\QrCodes;

use App\Models\ShortLink;
use App\Models\Workspace;

class ShortLinkOptions
{
    private const LIMIT = 50;

    public function for(Workspace $workspace, string $search = '', ?int $selectedId = null): array
    {
        $query = $workspace->shortLinks()->with('domain')->primary();
        $links = ShortLinkSearch::apply($query, $search)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        if ($selectedId && ! $links->contains('id', $selectedId)) {
            $selected = $workspace->shortLinks()->with('domain')->whereKey($selectedId)->first();

            if ($selected) {
                $links->prepend($selected);
            }
        }

        return $links->map(fn (ShortLink $link) => [
            'id' => $link->id,
            'short_url' => $link->shortUrl(),
            'destination_url' => $link->destination_url,
        ])->all();
    }
}

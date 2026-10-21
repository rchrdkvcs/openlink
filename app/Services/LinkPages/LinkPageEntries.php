<?php

namespace App\Services\LinkPages;

use App\Enums\LinkStatus;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Services\Analytics\Outcome;
use App\Services\ShortLinks\ShortLinkLifecycle;

class LinkPageEntries
{
    public function __construct(private readonly ShortLinkLifecycle $lifecycle) {}

    public function forEditor(LinkPage $linkPage): array
    {
        return $this->items($linkPage)->map(fn (LinkPageItem $item) => [
            'short_link_id' => $item->short_link_id,
            'label' => $item->label,
            'short_url' => $item->shortLink->shortUrl(),
            'destination_url' => $item->shortLink->destination_url,
            'display_label' => $this->displayLabel($item),
            'availability' => $this->availability($item),
        ])->values()->all();
    }

    public function forVisitors(LinkPage $linkPage): array
    {
        return $this->items($linkPage)
            ->filter(fn (LinkPageItem $item) => $this->availability($item) === LinkStatus::Active->value)
            ->map(fn (LinkPageItem $item) => [
                'label' => $this->displayLabel($item),
                'url' => $item->shortLink->shortUrl(),
            ])->values()->all();
    }

    private function items(LinkPage $linkPage)
    {
        return $linkPage->items()
            ->with('shortLink.domain')
            ->whereHas('shortLink', fn ($query) => $query->where('workspace_id', $linkPage->workspace_id))
            ->get();
    }

    private function availability(LinkPageItem $item): string
    {
        $status = $this->lifecycle->statusOf($item->shortLink);

        if ($status === LinkStatus::Active && ! $item->shortLink->domain->isUsable()) {
            return Outcome::DOMAIN_UNAVAILABLE;
        }

        return $status->value;
    }

    private function displayLabel(LinkPageItem $item): string
    {
        return filled($item->label)
            ? $item->label
            : $item->shortLink->domain->hostname.'/'.$item->shortLink->slug;
    }
}

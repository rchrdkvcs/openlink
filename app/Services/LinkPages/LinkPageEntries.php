<?php

namespace App\Services\LinkPages;

use App\Enums\LinkStatus;
use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Models\ShortLink;
use App\Models\Workspace;
use App\Services\Analytics\Outcome;
use App\Services\ShortLinks\ShortLinkLifecycle;

class LinkPageEntries
{
    public function __construct(
        private readonly ShortLinkLifecycle $lifecycle,
        private readonly LinkPageIcons $icons,
    ) {}

    public function forEditor(LinkPage $linkPage): array
    {
        return $this->items($linkPage)->map(fn (LinkPageItem $item) => [
            'short_link_id' => $item->short_link_id,
            'label' => $item->label,
            'short_url' => $item->shortLink->shortUrl(),
            'destination_url' => $item->shortLink->destination_url,
            'display_label' => $this->displayLabel($item),
            'availability' => $this->availability($item),
            'icon' => $this->icons->forLink($item->shortLink),
        ])->values()->all();
    }

    public function forVisitors(LinkPage $linkPage): array
    {
        return $this->items($linkPage)
            ->filter(fn (LinkPageItem $item) => $this->availability($item) === LinkStatus::Active->value)
            ->map(fn (LinkPageItem $item) => [
                'label' => $this->displayLabel($item),
                'url' => $item->shortLink->shortUrl(),
                'icon' => $this->icons->forLink($item->shortLink),
            ])->values()->all();
    }

    public function withAvailability(Workspace $workspace, array $options): array
    {
        $links = $workspace->shortLinks()->with('domain')->whereKey(array_column($options, 'id'))->get()->keyBy('id');

        return array_map(fn (array $option) => [
            ...$option,
            'availability' => $links->has($option['id']) ? $this->availabilityOf($links[$option['id']]) : LinkStatus::Archived->value,
            'icon' => $links->has($option['id']) ? $this->icons->forLink($links[$option['id']]) : null,
        ], $options);
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
        return $this->availabilityOf($item->shortLink);
    }

    private function availabilityOf(ShortLink $shortLink): string
    {
        $status = $this->lifecycle->statusOf($shortLink);

        if ($status === LinkStatus::Active && ! $shortLink->domain->isUsable()) {
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

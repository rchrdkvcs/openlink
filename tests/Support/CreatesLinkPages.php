<?php

namespace Tests\Support;

use App\Models\LinkPage;
use App\Models\ShortLink;
use App\Models\Workspace;

trait CreatesLinkPages
{
    use CreatesWorkspaces;

    protected function linkPage(Workspace $workspace, array $attributes = [], array $shortLinks = []): LinkPage
    {
        $page = LinkPage::create([
            'workspace_id' => $workspace->id,
            'name' => 'Spring conference',
            'path' => 'spring',
            'title' => 'Spring conference',
            'description' => 'Everything for the event.',
            ...$attributes,
        ]);

        foreach (array_values($shortLinks) as $position => $shortLink) {
            [$link, $label] = $shortLink instanceof ShortLink ? [$shortLink, null] : $shortLink;
            $page->items()->create(['short_link_id' => $link->id, 'label' => $label, 'position' => $position]);
        }

        return $page;
    }

    protected function publishedLinkPage(Workspace $workspace, array $attributes = [], array $shortLinks = []): LinkPage
    {
        return $this->linkPage($workspace, ['published_at' => now(), ...$attributes], $shortLinks);
    }
}

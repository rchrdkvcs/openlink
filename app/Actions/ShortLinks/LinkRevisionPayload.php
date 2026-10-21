<?php

namespace App\Actions\ShortLinks;

use App\Models\ShortLink;
use App\Models\ShortLinkRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LinkRevisionPayload
{
    public const PER_PAGE = 20;

    public function __construct(private readonly LinkRevisionRestore $restore) {}

    public function page(ShortLink $shortLink, int $page): array
    {
        $revisions = $this->paginate($shortLink, $page);

        return [
            'data' => array_map(fn (ShortLinkRevision $revision) => $this->summary($revision), $revisions->items()),
            'meta' => [
                'current_page' => $revisions->currentPage(),
                'last_page' => $revisions->lastPage(),
                'per_page' => $revisions->perPage(),
                'total' => $revisions->total(),
                'current_revision' => $shortLink->revision,
            ],
        ];
    }

    public function detail(ShortLink $shortLink, ShortLinkRevision $revision): array
    {
        return [
            ...$this->summary($revision),
            'schema_version' => $revision->schema_version,
            'snapshot' => $revision->snapshot,
            'restore' => $this->restore->preview($shortLink, $revision),
        ];
    }

    public function summary(ShortLinkRevision $revision): array
    {
        return [
            'number' => $revision->number,
            'action' => $revision->action->value,
            'channel' => $revision->channel->value,
            'actor' => $revision->actor?->only(['id', 'name']),
            'restored_from' => $revision->restored_from,
            'changes' => $revision->changes,
            'created_at' => $revision->created_at,
        ];
    }

    private function paginate(ShortLink $shortLink, int $page): LengthAwarePaginator
    {
        return $shortLink->revisions()
            ->with('actor')
            ->orderByDesc('number')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);
    }
}

<?php

namespace App\Actions\ShortLinks;

use App\Enums\BulkLinkAction;
use App\Enums\RevisionAction;
use App\Enums\WebhookEventType;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShortLinks\History\LinkHistory;
use App\Services\Webhooks\WebhookEvents;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BulkShortLinkMutation
{
    public const MAX_LINKS = 100;

    public function __construct(
        private readonly Gate $gate,
        private readonly ShortLinkMutation $shortLinks,
        private readonly ShortLinkTags $tags,
        private readonly LinkHistory $history,
        private readonly WebhookEvents $webhooks,
    ) {}

    public function apply(User $user, Workspace $workspace, BulkLinkAction $action, array $ids, ?int $folderId = null, array $tags = []): int
    {
        $this->gate->forUser($user)->authorize('editContent', $workspace);

        return DB::transaction(function () use ($user, $workspace, $action, $ids, $folderId, $tags): int {
            $links = $this->selection($workspace, $ids);

            match ($action) {
                BulkLinkAction::Move => $links->each(fn (ShortLink $shortLink) => $this->shortLinks->move($user, $shortLink, $folderId)),
                BulkLinkAction::AddTags => $this->recordedTags($user, $links, fn () => $this->tags->addToEach($links, $workspace->id, $tags)),
                BulkLinkAction::RemoveTags => $this->recordedTags($user, $links, fn () => $this->tags->removeFromEach($links, $workspace->id, $tags)),
                BulkLinkAction::Enable => $links->each(fn (ShortLink $shortLink) => $this->shortLinks->setEnabled($user, $shortLink, true)),
                BulkLinkAction::Disable => $links->each(fn (ShortLink $shortLink) => $this->shortLinks->setEnabled($user, $shortLink, false)),
                BulkLinkAction::Archive => $links->each(fn (ShortLink $shortLink) => $this->shortLinks->archive($user, $shortLink)),
            };

            return $links->count();
        });
    }

    private function recordedTags(User $user, Collection $links, callable $change): void
    {
        $before = $this->history->lockManyForChange($links);

        $change();

        $this->history->recordMany($links, RevisionAction::Updated, $before, $user);
        $links->each(fn (ShortLink $shortLink) => $this->webhooks->shortLink(WebhookEventType::ShortLinkUpdated, $shortLink));
    }

    private function selection(Workspace $workspace, array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === [] || count($ids) > self::MAX_LINKS) {
            throw ValidationException::withMessages([
                'ids' => 'Select between 1 and '.self::MAX_LINKS.' links.',
            ]);
        }

        $links = $workspace->shortLinks()
            ->whereKey($ids)
            ->with('domain')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(fn (ShortLink $shortLink) => $shortLink->setRelation('workspace', $workspace));

        $missing = count($ids) - $links->count();

        if ($missing > 0) {
            throw ValidationException::withMessages([
                'ids' => $missing.' selected '.Str::plural('link', $missing).' '.($missing === 1 ? 'is' : 'are')
                    .' no longer available. Refresh the list and try again.',
            ]);
        }

        return $links;
    }
}

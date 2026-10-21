<?php

namespace App\Actions\ShortLinks;

use App\Enums\BulkLinkAction;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
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
    ) {}

    public function apply(User $user, Workspace $workspace, BulkLinkAction $action, array $ids, ?int $folderId = null, array $tags = []): int
    {
        $this->gate->forUser($user)->authorize('editContent', $workspace);

        return DB::transaction(function () use ($user, $workspace, $action, $ids, $folderId, $tags): int {
            $links = $this->selection($workspace, $ids);

            $links->each(fn (ShortLink $shortLink) => match ($action) {
                BulkLinkAction::Move => $this->shortLinks->move($user, $shortLink, $folderId),
                BulkLinkAction::AddTags => $this->shortLinks->addTags($user, $shortLink, $tags),
                BulkLinkAction::RemoveTags => $this->shortLinks->removeTags($user, $shortLink, $tags),
                BulkLinkAction::Enable => $this->shortLinks->setEnabled($user, $shortLink, true),
                BulkLinkAction::Disable => $this->shortLinks->setEnabled($user, $shortLink, false),
                BulkLinkAction::Archive => $this->shortLinks->archive($user, $shortLink),
            });

            return $links->count();
        });
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

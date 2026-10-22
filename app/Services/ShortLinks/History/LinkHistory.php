<?php

namespace App\Services\ShortLinks\History;

use App\Enums\MutationChannel;
use App\Enums\RevisionAction;
use App\Models\ShortLink;
use App\Models\ShortLinkRevision;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LinkHistory
{
    public function __construct(
        private readonly LinkSnapshot $snapshots,
        private readonly Request $request,
    ) {}

    public function lockForChange(ShortLink $shortLink, ?int $expectedRevision): array
    {
        $current = (int) ShortLink::query()->whereKey($shortLink->id)->lockForUpdate()->value('revision');

        if ($expectedRevision !== null && $expectedRevision !== $current) {
            throw ValidationException::withMessages([
                'expected_revision' => 'This link was changed by someone else since you opened it. Reload it to see the latest version, then try again.',
            ]);
        }

        $shortLink->refresh();
        $before = $this->snapshots->capture($shortLink);

        if ($current === 0) {
            $this->append($shortLink, RevisionAction::Baseline, $before, [], null, null);
        }

        return $before;
    }

    public function record(ShortLink $shortLink, RevisionAction $action, ?array $before, ?User $actor, ?int $restoredFrom = null, array $extraChanges = []): ?ShortLinkRevision
    {
        $after = $this->snapshots->capture($shortLink);
        $changes = [...$this->snapshots->diff($before, $after), ...$extraChanges];

        if ($before !== null && $changes === []) {
            return null;
        }

        return $this->append($shortLink, $action, $after, $changes, $actor, $restoredFrom);
    }

    private function append(ShortLink $shortLink, RevisionAction $action, array $snapshot, array $changes, ?User $actor, ?int $restoredFrom): ShortLinkRevision
    {
        $number = (int) ShortLink::query()->whereKey($shortLink->id)->value('revision') + 1;
        ShortLink::query()->whereKey($shortLink->id)->update(['revision' => $number]);
        $shortLink->forceFill(['revision' => $number])->syncOriginalAttribute('revision');

        return $shortLink->revisions()->create([
            'workspace_id' => $shortLink->workspace_id,
            'number' => $number,
            'action' => $action,
            'channel' => $action === RevisionAction::Baseline ? MutationChannel::System : MutationChannel::of($this->request),
            'actor_id' => $actor?->id,
            'restored_from' => $restoredFrom,
            'schema_version' => LinkSnapshot::SCHEMA_VERSION,
            'snapshot' => $snapshot,
            'changes' => $changes,
        ]);
    }
}

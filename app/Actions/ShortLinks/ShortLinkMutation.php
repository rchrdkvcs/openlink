<?php

namespace App\Actions\ShortLinks;

use App\Enums\RevisionAction;
use App\Enums\WebhookEventType;
use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShortLinks\History\LinkHistory;
use App\Services\ShortLinks\ShortUrlAddress;
use App\Services\ShortLinks\SmartRouting;
use App\Services\Webhooks\WebhookEvents;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ShortLinkMutation
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ShortUrlAddresses $addresses,
        private readonly ShortLinkTags $tags,
        private readonly SmartRouting $routing,
        private readonly LinkHistory $history,
        private readonly WebhookEvents $webhooks,
    ) {}

    public function create(User $user, Workspace $workspace, array $data): ShortLink
    {
        $this->gate->forUser($user)->authorize('editContent', $workspace);

        $address = $this->addresses->forNewLink($workspace, $this->integer($data, 'domain_id'), $data['slug'] ?? null);
        $address->rejectLoop($data['destination_url'], 'destination_url');

        return DB::transaction(function () use ($user, $workspace, $address, $data): ShortLink {
            $shortLink = new ShortLink(['workspace_id' => $workspace->id]);
            $this->fill($shortLink, $address, [
                'is_enabled' => true,
                ...array_filter($data, fn ($value) => $value !== null),
            ]);
            $shortLink->save();

            $this->tags->replace($shortLink, $data['tags'] ?? null);
            $this->routing->sync($shortLink, $data['routing_rules'] ?? []);
            $this->history->record($shortLink, RevisionAction::Created, null, $user);
            $this->webhooks->shortLink(WebhookEventType::ShortLinkCreated, $shortLink);

            return $shortLink;
        });
    }

    public function update(User $user, ShortLink $shortLink, array $data): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);

        $address = $this->addresses->forExistingLink($shortLink, $this->integer($data, 'domain_id'), $data['slug'] ?? null);
        $address->rejectLoop($data['destination_url'], 'destination_url');

        return $this->recorded($user, $shortLink, RevisionAction::Updated, $this->integer($data, 'expected_revision'), function () use ($shortLink, $address, $data): array {
            $wasProtected = $shortLink->hasPassword();
            $this->fill($shortLink, $address, $data);
            $shortLink->save();

            if (array_key_exists('routing_rules', $data)) {
                $this->routing->sync($shortLink, $data['routing_rules'] ?? []);
            }

            if (array_key_exists('tags', $data)) {
                $this->tags->replace($shortLink, $data['tags']);
            }

            $this->webhooks->shortLink(WebhookEventType::ShortLinkUpdated, $shortLink);

            return $wasProtected && $shortLink->hasPassword() && $shortLink->wasChanged('password_hash')
                ? [['field' => 'password_changed', 'before' => null, 'after' => null]]
                : [];
        });
    }

    public function updateRouting(User $user, ShortLink $shortLink, string $destinationUrl, array $rules): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);
        ShortUrlAddress::of($shortLink)->rejectLoop($destinationUrl, 'destination_url');

        return $this->recorded($user, $shortLink, RevisionAction::Updated, null, function () use ($shortLink, $destinationUrl, $rules): void {
            $shortLink->update(['destination_url' => $destinationUrl]);
            $this->routing->sync($shortLink, $rules);
            $this->webhooks->shortLink(WebhookEventType::ShortLinkUpdated, $shortLink);
        });
    }

    public function restore(User $user, ShortLink $shortLink, int $sourceRevision, int $expectedRevision, array $data): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);

        $address = $this->addresses->forExistingLink($shortLink, null, null);
        $address->rejectLoop($data['destination_url'], 'destination_url');

        return $this->recorded($user, $shortLink, RevisionAction::Restored, $expectedRevision, function () use ($shortLink, $address, $data): void {
            $this->fill($shortLink, $address, $data);
            $shortLink->archived_at = $data['is_archived'] ? ($shortLink->archived_at ?? now()) : null;
            $shortLink->save();

            $this->routing->sync($shortLink, $data['routing_rules']);
            $this->tags->replace($shortLink, $data['tags']);
            $this->webhooks->shortLink(WebhookEventType::ShortLinkUpdated, $shortLink);
        }, $sourceRevision);
    }

    public function move(User $user, ShortLink $shortLink, ?int $folderId, ?int $expectedRevision = null): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);

        return $this->recorded($user, $shortLink, RevisionAction::Moved, $expectedRevision, function () use ($shortLink, $folderId): void {
            $shortLink->update(['folder_id' => $folderId]);
            $this->webhooks->shortLink(WebhookEventType::ShortLinkUpdated, $shortLink);
        });
    }

    public function archive(User $user, ShortLink $shortLink, ?int $expectedRevision = null): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);

        return $this->recorded($user, $shortLink, RevisionAction::Archived, $expectedRevision, function () use ($shortLink): void {
            $shortLink->update(['archived_at' => $shortLink->archived_at ?? now()]);
            $this->webhooks->shortLink(WebhookEventType::ShortLinkArchived, $shortLink);
        });
    }

    public function setEnabled(User $user, ShortLink $shortLink, bool $enabled): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);

        return $this->recorded($user, $shortLink, RevisionAction::Updated, null, function () use ($shortLink, $enabled): void {
            $shortLink->update(['is_enabled' => $enabled]);
            $this->webhooks->shortLink(WebhookEventType::ShortLinkUpdated, $shortLink);
        });
    }

    public function delete(User $user, ShortLink $shortLink): void
    {
        $this->gate->forUser($user)->authorize('delete', $shortLink);
        $shortLink->delete();
    }

    private function recorded(User $user, ShortLink $shortLink, RevisionAction $action, ?int $expectedRevision, callable $change, ?int $restoredFrom = null): ShortLink
    {
        return DB::transaction(function () use ($user, $shortLink, $action, $expectedRevision, $change, $restoredFrom): ShortLink {
            $before = $this->history->lockForChange($shortLink, $expectedRevision);
            $extraChanges = $change() ?? [];
            $this->history->record($shortLink, $action, $before, $user, $restoredFrom, $extraChanges);

            return $shortLink;
        });
    }

    private function fill(ShortLink $shortLink, ShortUrlAddress $address, array $data): void
    {
        $shortLink->fill([
            'folder_id' => $this->integer($data, 'folder_id'),
            'domain_id' => $address->domain->id,
            'slug' => $address->slug,
            'destination_url' => $data['destination_url'],
            'fallback_url' => $data['fallback_url'] ?? null,
            'is_enabled' => (bool) $data['is_enabled'],
            'activates_at' => $data['activates_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'visit_limit' => $this->integer($data, 'visit_limit'),
        ]);
        $shortLink->setRelation('domain', $address->domain);

        if (array_key_exists('password', $data) || ! $shortLink->exists) {
            $shortLink->password_hash = filled($data['password'] ?? null) ? Hash::make($data['password']) : null;
        }
    }

    private function integer(array $data, string $key): ?int
    {
        return filled($data[$key] ?? null) ? (int) $data[$key] : null;
    }
}

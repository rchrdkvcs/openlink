<?php

namespace App\Actions\ShortLinks;

use App\Models\ShortLink;
use App\Models\User;
use App\Models\Workspace;
use App\Services\ShortLinks\ShortUrlAddress;
use App\Services\ShortLinks\SmartRouting;
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
    ) {}

    public function create(User $user, Workspace $workspace, array $data): ShortLink
    {
        $this->gate->forUser($user)->authorize('editContent', $workspace);

        $address = $this->addresses->forNewLink($workspace, $this->integer($data, 'domain_id'), $data['slug'] ?? null);
        $address->rejectLoop($data['destination_url'], 'destination_url');

        return DB::transaction(function () use ($workspace, $address, $data): ShortLink {
            $shortLink = new ShortLink(['workspace_id' => $workspace->id]);
            $this->fill($shortLink, $address, [
                'is_enabled' => true,
                ...array_filter($data, fn ($value) => $value !== null),
            ]);
            $shortLink->save();

            $this->tags->replace($shortLink, $data['tags'] ?? null);
            $this->routing->sync($shortLink, $data['routing_rules'] ?? []);

            return $shortLink;
        });
    }

    public function update(User $user, ShortLink $shortLink, array $data): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);

        $address = $this->addresses->forExistingLink($shortLink, $this->integer($data, 'domain_id'), $data['slug'] ?? null);
        $address->rejectLoop($data['destination_url'], 'destination_url');

        return DB::transaction(function () use ($shortLink, $address, $data): ShortLink {
            $this->fill($shortLink, $address, $data);
            $shortLink->save();

            if (array_key_exists('routing_rules', $data)) {
                $this->routing->sync($shortLink, $data['routing_rules'] ?? []);
            }

            if (array_key_exists('tags', $data)) {
                $this->tags->replace($shortLink, $data['tags']);
            }

            return $shortLink;
        });
    }

    public function move(User $user, ShortLink $shortLink, ?int $folderId): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);
        $shortLink->update(['folder_id' => $folderId]);

        return $shortLink;
    }

    public function archive(User $user, ShortLink $shortLink): ShortLink
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);
        $shortLink->update(['archived_at' => now()]);

        return $shortLink;
    }

    public function delete(User $user, ShortLink $shortLink): void
    {
        $this->gate->forUser($user)->authorize('delete', $shortLink);
        $shortLink->delete();
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

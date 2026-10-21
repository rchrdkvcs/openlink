<?php

namespace App\Actions\ShortLinks;

use App\Http\Requests\ShortLinks\ShortLinkRequest;
use App\Models\ShortLink;
use App\Models\ShortLinkRevision;
use App\Models\User;
use App\Services\ShortLinks\History\LinkSnapshot;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LinkRevisionRestore
{
    private const RESTORABLE = [
        'destination_url',
        'fallback_url',
        'is_enabled',
        'is_archived',
        'activates_at',
        'expires_at',
        'visit_limit',
        'folder',
        'tags',
        'routing_rules',
    ];

    public function __construct(
        private readonly LinkSnapshot $snapshots,
        private readonly ShortLinkMutation $mutation,
    ) {}

    public function preview(ShortLink $shortLink, ShortLinkRevision $revision): array
    {
        if ($revision->schema_version !== LinkSnapshot::SCHEMA_VERSION) {
            return ['restorable' => false, 'reason' => $this->unsupported($revision), 'changes' => [], 'notes' => []];
        }

        $current = $this->snapshots->capture($shortLink);
        $target = $this->resolvedTarget($shortLink, $revision->snapshot);
        $changes = $this->snapshots->diff(
            array_intersect_key($current, array_flip(self::RESTORABLE)),
            array_intersect_key($target, array_flip(self::RESTORABLE)),
        );
        $blocker = match (true) {
            $revision->number === $shortLink->revision => 'This is the current revision.',
            $changes === [] => 'This revision matches the current configuration.',
            default => null,
        };

        return [
            'restorable' => $blocker === null,
            'reason' => $blocker,
            'changes' => $changes,
            'notes' => $blocker === null ? $this->notes($current, $revision->snapshot, $target) : [],
        ];
    }

    public function restore(User $user, ShortLink $shortLink, ShortLinkRevision $revision, int $expectedRevision): ShortLink
    {
        if ($revision->schema_version !== LinkSnapshot::SCHEMA_VERSION) {
            throw ValidationException::withMessages(['revision' => $this->unsupported($revision)]);
        }

        $target = $this->resolvedTarget($shortLink, $revision->snapshot);
        $data = [
            'destination_url' => $target['destination_url'],
            'fallback_url' => $target['fallback_url'],
            'is_enabled' => $target['is_enabled'],
            'is_archived' => $target['is_archived'],
            'activates_at' => $target['activates_at'],
            'expires_at' => $target['expires_at'],
            'visit_limit' => $target['visit_limit'],
            'folder_id' => $target['folder']['id'] ?? null,
            'tags' => collect($target['tags'])->pluck('name')->implode(', '),
            'routing_rules' => $target['routing_rules'],
        ];

        Validator::make($data, ShortLinkRequest::configurationRules())->validate();

        return $this->mutation->restore($user, $shortLink, $revision->number, $expectedRevision, $data);
    }

    private function unsupported(ShortLinkRevision $revision): string
    {
        return "Revision {$revision->number} was recorded in a format this version of Openlink can't restore. Copy its settings from the history and edit the link instead.";
    }

    private function resolvedTarget(ShortLink $shortLink, array $snapshot): array
    {
        $folder = $snapshot['folder'] ?? null;
        $folderExists = $folder !== null && $shortLink->workspace->folders()->whereKey($folder['id'])->exists();
        $rules = $shortLink->routingRules()->with('variants')->get()->keyBy('id');
        $tags = $shortLink->workspace->tags()
            ->whereIn('name', array_column($snapshot['tags'] ?? [], 'name'))
            ->pluck('id', 'name');

        return [
            ...$snapshot,
            'folder' => $folderExists ? $folder : null,
            'tags' => array_map(fn (array $tag) => ['id' => $tags->get($tag['name']), 'name' => $tag['name']], $snapshot['tags'] ?? []),
            'routing_rules' => array_map(function (array $rule) use ($rules): array {
                $current = $rules->get($rule['id'] ?? 0);
                $variants = $current?->variants->keyBy('id');

                return [
                    ...$rule,
                    'id' => $current?->id,
                    'variants' => array_map(fn (array $variant) => [
                        ...$variant,
                        'id' => $variants?->get($variant['id'] ?? 0)?->id,
                    ], $rule['variants'] ?? []),
                ];
            }, $snapshot['routing_rules'] ?? []),
        ];
    }

    private function notes(array $current, array $snapshot, array $target): array
    {
        $notes = [];

        if (($snapshot['folder'] ?? null) !== null && $target['folder'] === null) {
            $notes[] = "The folder “{$snapshot['folder']['name']}” no longer exists. The link will be left without a folder.";
        }

        $recreatedTags = collect($target['tags'])->whereNull('id')->pluck('name');

        if ($recreatedTags->isNotEmpty()) {
            $notes[] = 'These tags were deleted and will be created again: '.$recreatedTags->implode(', ').'.';
        }

        $recreated = collect($target['routing_rules'])->whereNull('id')->pluck('name');

        if ($recreated->isNotEmpty()) {
            $notes[] = 'Deleted routing rules come back as new rules, so their earlier analytics stay separate: '.$recreated->implode(', ').'.';
        }

        if (($snapshot['has_password'] ?? false) !== $current['has_password']) {
            $notes[] = $current['has_password']
                ? 'Passwords are never restored. The link keeps its current password.'
                : 'Passwords are never restored. The link stays without a password.';
        }

        if (($snapshot['short_url'] ?? null) !== $current['short_url']) {
            $notes[] = "The short URL stays {$current['short_url']}. Restoring never changes the address.";
        }

        return $notes;
    }
}

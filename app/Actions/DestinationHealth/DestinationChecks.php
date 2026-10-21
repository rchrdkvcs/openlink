<?php

namespace App\Actions\DestinationHealth;

use App\Enums\DestinationHealthStatus;
use App\Jobs\CheckDestination;
use App\Models\DestinationCheck;
use App\Models\ShortLink;
use App\Models\User;
use App\Services\DestinationHealth\DestinationProbeResult;
use App\Services\DestinationHealth\DestinationReferences;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DestinationChecks
{
    public const CONFIRMATION_THRESHOLD = 2;

    public const RECHECK_AFTER_HOURS = 6;

    public const INCIDENT_RECHECK_AFTER_MINUTES = 60;

    public const CONFIRM_AFTER_MINUTES = 10;

    private const REQUEST_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly Gate $gate,
        private readonly DestinationReferences $references,
    ) {}

    public function requestForShortLink(User $user, ShortLink $shortLink): Collection
    {
        $this->gate->forUser($user)->authorize('update', $shortLink);

        return collect($this->references->urlsFor($shortLink))
            ->map(fn (string $url) => $this->queue($this->ensure($shortLink->workspace_id, $url)));
    }

    public function retry(User $user, DestinationCheck $check): DestinationCheck
    {
        $this->gate->forUser($user)->authorize('editContent', $check->workspace);

        return $this->queue($check);
    }

    public function ensure(int $workspaceId, string $url): DestinationCheck
    {
        return DestinationCheck::query()->createOrFirst(
            ['workspace_id' => $workspaceId, 'url_hash' => DestinationCheck::hashFor($url)],
            ['url' => $url],
        );
    }

    public function queue(DestinationCheck $check): DestinationCheck
    {
        if ($check->isPending() && $check->requested_at->greaterThan(now()->subSeconds(self::REQUEST_COOLDOWN_SECONDS))) {
            return $check;
        }

        $check->forceFill(['requested_at' => now()])->save();
        dispatch(CheckDestination::for($check));

        return $check;
    }

    public function record(DestinationCheck $check, DestinationProbeResult $result): DestinationCheck
    {
        $now = now();
        $attributes = [
            'status' => $result->status,
            'http_status' => $result->httpStatus,
            'detail' => $result->detail === null ? null : Str::limit($result->detail, 250),
            'checked_at' => $now,
        ];

        if ($result->status->isFailure()) {
            $failures = $check->consecutive_failures + 1;
            $attributes += [
                'consecutive_failures' => $failures,
                'failing_since' => $check->failing_since ?? $now,
                'incident_opened_at' => $check->incident_opened_at ?? ($failures >= self::CONFIRMATION_THRESHOLD ? $now : null),
            ];
        } elseif ($result->status === DestinationHealthStatus::Reachable) {
            $attributes += [
                'consecutive_failures' => 0,
                'failing_since' => null,
                'incident_opened_at' => null,
                'recovered_at' => $check->hasIncident() ? $now : $check->recovered_at,
            ];
        }

        $check->forceFill($attributes)->save();

        return $check;
    }

    public function discover(): int
    {
        $created = 0;

        ShortLink::query()
            ->whereNull('archived_at')
            ->with(DestinationReferences::RELATIONS)
            ->chunkById(500, function (Collection $links) use (&$created): void {
                $rows = $links
                    ->flatMap(fn (ShortLink $link) => collect($this->references->urlsFor($link))
                        ->map(fn (string $url) => [
                            'workspace_id' => $link->workspace_id,
                            'url' => $url,
                            'url_hash' => DestinationCheck::hashFor($url),
                            'status' => DestinationHealthStatus::Unchecked->value,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]))
                    ->unique(fn (array $row) => $row['workspace_id'].':'.$row['url_hash'])
                    ->values()
                    ->all();

                $created += DestinationCheck::query()->insertOrIgnore($rows);
            });

        return $created;
    }

    public function prune(): int
    {
        $pruned = 0;

        DestinationCheck::query()
            ->whereNot(fn (Builder $query) => $query->referenced())
            ->select('id')
            ->chunkById(500, function (Collection $checks) use (&$pruned): void {
                $pruned += DestinationCheck::query()->whereKey($checks->pluck('id')->all())->delete();
            });

        return $pruned;
    }

    public function due(int $limit): Collection
    {
        return DestinationCheck::query()
            ->referenced()
            ->where(fn (Builder $query) => $query
                ->whereNull('requested_at')
                ->orWhereColumn('requested_at', '<=', 'checked_at')
                ->orWhere('requested_at', '<=', now()->subMinutes(DestinationCheck::PENDING_FOR_MINUTES)))
            ->where(fn (Builder $query) => $query
                ->whereNull('checked_at')
                ->orWhere('checked_at', '<=', now()->subHours(self::RECHECK_AFTER_HOURS))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNotNull('incident_opened_at')
                    ->where('checked_at', '<=', now()->subMinutes(self::INCIDENT_RECHECK_AFTER_MINUTES)))
                ->orWhere(fn (Builder $query) => $query
                    ->where('consecutive_failures', '>', 0)
                    ->whereNull('incident_opened_at')
                    ->where('checked_at', '<=', now()->subMinutes(self::CONFIRM_AFTER_MINUTES))))
            ->orderByRaw('checked_at asc nulls first')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}

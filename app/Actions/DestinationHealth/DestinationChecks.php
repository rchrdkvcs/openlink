<?php

namespace App\Actions\DestinationHealth;

use App\Enums\DestinationHealthStatus;
use App\Jobs\CheckDestination;
use App\Models\DestinationCheck;
use App\Models\ShortLink;
use App\Models\User;
use App\Services\DestinationHealth\DestinationProbeResult;
use App\Services\DestinationHealth\DestinationReferences;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DestinationChecks
{
    public const CONFIRMATION_THRESHOLD = 2;

    public const RECHECK_AFTER_HOURS = 6;

    public const INCIDENT_RECHECK_AFTER_MINUTES = 60;

    public const CONFIRM_AFTER_MINUTES = 10;

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
        $connection = config('queue.default');

        if (! in_array(config("queue.connections.$connection.driver"), ['redis', 'database', 'sqs', 'beanstalkd'], true)) {
            throw ValidationException::withMessages([
                'destination_health' => 'Destination Checks need an asynchronous queue and a running worker. Ask the instance administrator to configure them.',
            ]);
        }

        if ($check->isPending() && ! $check->canRetry()) {
            return $check;
        }

        $queued = DestinationCheck::query()
            ->whereKey($check->id)
            ->where('generation', $check->generation)
            ->update(['requested_at' => now(), 'started_at' => null, 'execution_state' => 'queued', 'generation' => (string) Str::uuid()]);
        $check->refresh();

        if (! $queued) {
            return $check;
        }

        $job = CheckDestination::for($check);

        try {
            dispatch($job);
        } catch (Throwable $exception) {
            $this->releaseUniqueLock($job);
            DestinationCheck::query()->whereKey($check->id)->where('generation', $job->generation)->update(['execution_state' => 'failed']);
            report($exception);

            throw ValidationException::withMessages([
                'destination_health' => 'The Destination Check could not be queued. Try again or ask the instance administrator to check the worker.',
            ]);
        }

        return $check;
    }

    private function releaseUniqueLock(CheckDestination $job): void
    {
        try {
            app(UniqueLock::class)->release($job);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function record(DestinationCheck $check, DestinationProbeResult $result): DestinationCheck
    {
        $now = now();
        $attributes = [
            'status' => $result->status,
            'http_status' => $result->httpStatus,
            'detail' => $result->detail === null ? null : Str::limit($result->detail, 250),
            'checked_at' => $now,
            'execution_state' => 'completed',
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

        DestinationCheck::query()
            ->whereKey($check->id)
            ->where('generation', $check->generation)
            ->where('execution_state', 'running')
            ->update($attributes);
        $check->refresh();

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
                ->whereNotIn('execution_state', ['queued', 'running'])
                ->orWhere(fn (Builder $legacy) => $legacy
                    ->whereNull('execution_state')
                    ->where(fn (Builder $requested) => $requested
                        ->whereNull('requested_at')
                        ->orWhereColumn('requested_at', '<=', 'checked_at'))))
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

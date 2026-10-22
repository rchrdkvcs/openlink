<?php

namespace App\Services\Diagnostics;

use App\Enums\CheckState;
use App\Models\DiagnosticObservation;
use Carbon\CarbonInterface;

class Heartbeats
{
    public const STALE_AFTER_CADENCES = 3;

    private ?int $lastWorkerBeat = null;

    public function record(string $service): void
    {
        $now = now();

        DiagnosticObservation::query()->upsert(
            [['name' => $service, 'state' => CheckState::Healthy->value, 'checked_at' => $now, 'succeeded_at' => $now]],
            ['name'],
            ['state', 'checked_at', 'succeeded_at'],
        );
    }

    public function workerActive(string $connection): void
    {
        if (config("queue.connections.{$connection}.driver") === 'sync') {
            return;
        }

        $now = now()->getTimestamp();

        if ($this->lastWorkerBeat !== null && $now - $this->lastWorkerBeat < $this->cadenceSeconds()) {
            return;
        }

        rescue(function () use ($now) {
            $this->record(DiagnosticObservation::WORKER);
            $this->lastWorkerBeat = $now;
        }, report: false);
    }

    public function freshness(?CarbonInterface $beatAt): CheckState
    {
        if ($beatAt === null) {
            return CheckState::Missing;
        }

        return $beatAt->greaterThanOrEqualTo(now()->subMinutes($this->staleAfterMinutes()))
            ? CheckState::Healthy
            : CheckState::Stale;
    }

    public function cadenceMinutes(): int
    {
        return min(30, max(1, (int) config('openlink.diagnostics.heartbeat_minutes')));
    }

    public function staleAfterMinutes(): int
    {
        return $this->cadenceMinutes() * self::STALE_AFTER_CADENCES;
    }

    public function schedulerCron(): string
    {
        $minutes = $this->cadenceMinutes();

        return $minutes === 1 ? '* * * * *' : "*/{$minutes} * * * *";
    }

    private function cadenceSeconds(): int
    {
        return $this->cadenceMinutes() * 60;
    }
}

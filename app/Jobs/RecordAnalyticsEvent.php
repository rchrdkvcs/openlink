<?php

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RecordAnalyticsEvent implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly array $event) {}

    public function handle(): void
    {
        try {
            AnalyticsEvent::query()->create($this->event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}

<?php

namespace App\Jobs;

use App\Services\Analytics\AnalyticsRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordAnalyticsEvent implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $event  Captured analytics_events row.
     */
    public function __construct(public readonly array $event) {}

    public function handle(AnalyticsRecorder $recorder): void
    {
        $recorder->persist($this->event);
    }
}

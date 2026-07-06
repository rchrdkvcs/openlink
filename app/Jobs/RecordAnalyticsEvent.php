<?php

namespace App\Jobs;

use App\Actions\Analytics\RecordAnalytics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordAnalyticsEvent implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $event  Captured analytics_events row.
     */
    public function __construct(public readonly array $event) {}

    public function handle(RecordAnalytics $recorder): void
    {
        $recorder->persist($this->event);
    }
}

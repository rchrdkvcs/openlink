<?php

namespace App\Jobs;

use App\Services\AnalyticsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordAnalyticsEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly ?int $shortLinkId,
        public readonly ?int $qrCodeId,
        public readonly string $metric,
        public readonly string $outcome,
        public readonly ?string $referrerHost,
        public readonly ?string $country,
        public readonly string $deviceType,
        public readonly string $browser,
        public readonly string $os,
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analytics): void
    {
        $analytics->recordNow(
            shortLinkId: $this->shortLinkId,
            qrCodeId: $this->qrCodeId,
            metric: $this->metric,
            outcome: $this->outcome,
            referrerHost: $this->referrerHost,
            country: $this->country,
            deviceType: $this->deviceType,
            browser: $this->browser,
            os: $this->os,
        );
    }
}

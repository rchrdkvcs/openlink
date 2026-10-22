<?php

namespace App\Jobs;

use App\Actions\Analytics\BuildWorkspaceSummary;
use App\Mail\WorkspaceSummaryMail;
use App\Models\AnalyticsSummaryDelivery;
use App\Services\Analytics\SummaryPeriod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SendWorkspaceSummary implements ShouldQueue
{
    use Queueable;

    public const MAIL_FAILURE = 'The mail server did not accept the summary. Check the mail configuration.';

    public const GENERIC_FAILURE = 'The summary could not be sent. Check the application logs.';

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public readonly int $deliveryId) {}

    public function handle(BuildWorkspaceSummary $summaries): void
    {
        $failure = DB::transaction(function () use ($summaries) {
            $delivery = AnalyticsSummaryDelivery::query()->lockForUpdate()->find($this->deliveryId);

            if (! $delivery || $delivery->isFinished()) {
                return null;
            }

            if (! $this->stillWanted($delivery)) {
                $delivery->update(['status' => AnalyticsSummaryDelivery::STATUS_CANCELLED]);

                return null;
            }

            $delivery->attempts++;

            try {
                $schedule = $delivery->schedule;
                $period = new SummaryPeriod(
                    $delivery->period_start->setTimezone($schedule->timezone),
                    $delivery->period_end->setTimezone($schedule->timezone),
                    $schedule->timezone,
                );

                Mail::to($delivery->user)->send(new WorkspaceSummaryMail($summaries->build($schedule->workspace, $period)));
            } catch (Throwable $exception) {
                $this->markFailed($delivery, $exception);

                return $exception;
            }

            $delivery->forceFill([
                'status' => AnalyticsSummaryDelivery::STATUS_SENT,
                'sent_at' => now(),
                'failure' => null,
            ])->save();

            return null;
        });

        if ($failure) {
            throw $failure;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = AnalyticsSummaryDelivery::query()->find($this->deliveryId);

        if ($delivery && ! $delivery->isFinished() && $delivery->status !== AnalyticsSummaryDelivery::STATUS_FAILED) {
            $this->markFailed($delivery, $exception);
        }
    }

    public static function describe(?Throwable $exception): string
    {
        return $exception instanceof TransportExceptionInterface ? self::MAIL_FAILURE : self::GENERIC_FAILURE;
    }

    private function stillWanted(AnalyticsSummaryDelivery $delivery): bool
    {
        $schedule = $delivery->schedule;

        return $schedule->is_enabled
            && $schedule->currentRecipients()
                ->where('workspace_members.user_id', $delivery->user_id)
                ->exists();
    }

    private function markFailed(AnalyticsSummaryDelivery $delivery, ?Throwable $exception): void
    {
        $delivery->forceFill([
            'status' => AnalyticsSummaryDelivery::STATUS_FAILED,
            'failed_at' => now(),
            'failure' => self::describe($exception),
        ])->save();
    }
}

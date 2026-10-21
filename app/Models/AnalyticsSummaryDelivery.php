<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsSummaryDelivery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'period_date' => 'immutable_date',
            'period_start' => 'immutable_datetime',
            'period_end' => 'immutable_datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AnalyticsSummarySchedule::class, 'analytics_summary_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function cancelPendingFor(int $workspaceId, int $userId): void
    {
        self::query()
            ->where('user_id', $userId)
            ->where('status', self::STATUS_PENDING)
            ->whereIn('analytics_summary_schedule_id', AnalyticsSummarySchedule::query()->where('workspace_id', $workspaceId)->select('id'))
            ->update(['status' => self::STATUS_CANCELLED]);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_CANCELLED], true);
    }
}

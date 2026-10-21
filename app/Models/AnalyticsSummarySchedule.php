<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsSummarySchedule extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(WorkspaceMember::class, 'analytics_summary_recipients');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AnalyticsSummaryDelivery::class);
    }
}

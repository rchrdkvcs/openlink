<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'is_bot' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class);
    }

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function bioPage(): BelongsTo
    {
        return $this->belongsTo(BioPage::class);
    }

    public function bioElement(): BelongsTo
    {
        return $this->belongsTo(BioElement::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function routingRule(): BelongsTo
    {
        return $this->belongsTo(RoutingRule::class);
    }

    public function routingVariant(): BelongsTo
    {
        return $this->belongsTo(RoutingVariant::class);
    }

    /** Human traffic that reached the destination URL. */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('outcome', 'success')->where('is_bot', false);
    }
}

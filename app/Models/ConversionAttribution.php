<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionAttribution extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'converted_at' => 'datetime',
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

    public function routingRule(): BelongsTo
    {
        return $this->belongsTo(RoutingRule::class);
    }

    public function routingVariant(): BelongsTo
    {
        return $this->belongsTo(RoutingVariant::class);
    }

    public function isConverted(): bool
    {
        return $this->converted_at !== null;
    }
}

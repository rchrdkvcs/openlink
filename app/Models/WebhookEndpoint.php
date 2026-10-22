<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    public const MAX_PER_WORKSPACE = 10;

    public const SECRET_OVERLAP_HOURS = 24;

    protected $guarded = [];

    protected $hidden = ['secret', 'previous_secret'];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'previous_secret' => 'encrypted',
            'previous_secret_expires_at' => 'datetime',
            'is_enabled' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function limitReachedIn(Workspace $workspace): bool
    {
        return $workspace->webhookEndpoints()->count() >= self::MAX_PER_WORKSPACE;
    }

    public function signingSecrets(): array
    {
        $previous = $this->previous_secret !== null && $this->previous_secret_expires_at?->isFuture()
            ? [$this->previous_secret]
            : [];

        return [$this->secret, ...$previous];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}

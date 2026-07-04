<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Domain extends Model
{
    public const STATUS_PENDING = 'pending_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_FAILED = 'failed_verification';
    public const STATUS_DISABLED = 'disabled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function shortLinks(): HasMany
    {
        return $this->hasMany(ShortLink::class);
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_VERIFIED && $this->disabled_at === null;
    }

    protected static function booted(): void
    {
        static::saved(function (Domain $domain): void {
            $domain->shortLinks()->pluck('slug')->each(
                fn (string $slug) => Cache::forget("resolution:{$domain->hostname}:{$slug}")
            );
        });

        static::deleting(function (Domain $domain): void {
            $domain->shortLinks()->pluck('slug')->each(
                fn (string $slug) => Cache::forget("resolution:{$domain->hostname}:{$slug}")
            );
        });
    }
}

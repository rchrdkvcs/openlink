<?php

namespace App\Models;

use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    public const STATUS_PENDING = 'pending_verification';

    public const STATUS_OWNERSHIP_VERIFIED = 'ownership_verified';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed_verification';

    public const STATUS_DISABLED = 'disabled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
            'dns_pointed_at' => 'datetime',
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
        return $this->status === self::STATUS_ACTIVE && $this->disabled_at === null;
    }

    public function isOwnershipVerified(): bool
    {
        return in_array($this->status, [self::STATUS_OWNERSHIP_VERIFIED, self::STATUS_ACTIVE], true);
    }

    public function activate(): void
    {
        $this->forceFill([
            'status' => self::STATUS_ACTIVE,
            'dns_pointed_at' => $this->dns_pointed_at ?? now(),
            'dns_check_error' => null,
        ])->save();
    }

    protected static function booted(): void
    {
        static::saved(function (Domain $domain): void {
            app(ShortUrlCache::class)->forgetForDomain($domain);
        });

        static::deleting(function (Domain $domain): void {
            app(ShortUrlCache::class)->forgetForDomain($domain);
        });
    }
}

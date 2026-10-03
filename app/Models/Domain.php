<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    public const STATUS_PENDING = DomainStatus::Pending->value;

    public const STATUS_OWNERSHIP_VERIFIED = DomainStatus::OwnershipVerified->value;

    public const STATUS_ACTIVE = DomainStatus::Active->value;

    public const STATUS_FAILED = DomainStatus::Failed->value;

    public const STATUS_DISABLED = DomainStatus::Disabled->value;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'is_default' => 'boolean',
            'verified_at' => 'datetime',
            'dns_pointed_at' => 'datetime',
            'disabled_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public static function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(preg_replace('/^https?:\/\//i', '', trim($hostname)));

        return trim($hostname, '/');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function shortLinks(): HasMany
    {
        return $this->hasMany(ShortLink::class);
    }

    public function isDisabled(): bool
    {
        return $this->status === DomainStatus::Disabled || $this->disabled_at !== null;
    }

    public function isUsable(): bool
    {
        return $this->status === DomainStatus::Active && ! $this->isDisabled();
    }

    public function isOwnershipVerified(): bool
    {
        return $this->status->provesOwnership();
    }

    public function isPointed(): bool
    {
        return $this->status === DomainStatus::Active || $this->dns_pointed_at !== null;
    }

    public function verificationTxtName(): string
    {
        return '_openlink.'.$this->hostname;
    }

    public function verificationTxtValue(): string
    {
        return 'openlink-verification='.$this->verification_token;
    }

    protected static function booted(): void
    {
        static::saved(function (Domain $domain): void {
            if ($domain->wasChanged('hostname')) {
                app(ShortUrlCache::class)->forgetForDomain($domain, $domain->getOriginal('hostname'));
            }
        });

        static::deleting(function (Domain $domain): void {
            app(ShortUrlCache::class)->forgetForDomain($domain);
        });
    }
}

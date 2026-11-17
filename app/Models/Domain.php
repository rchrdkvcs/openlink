<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Enums\EdgeStatus;
use App\Jobs\ReleaseEdgeHostname;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Database\Eloquent\Builder;
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

    public const ABANDONED_AFTER_DAYS = 30;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'edge_status' => EdgeStatus::class,
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

    public function appAssociations(): HasMany
    {
        return $this->hasMany(AppAssociation::class);
    }

    public function scopeAbandoned(Builder $query): Builder
    {
        return $query
            ->whereIn('status', DomainStatus::awaitingChecks())
            ->where('created_at', '<', now()->subDays(self::ABANDONED_AFTER_DAYS));
    }

    public function scopeAwaitingChecks(Builder $query): Builder
    {
        return $query
            ->whereNotNull('workspace_id')
            ->whereNull('disabled_at')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->whereIn('status', DomainStatus::awaitingChecks())
                    ->where('created_at', '>=', now()->subDays(self::ABANDONED_AFTER_DAYS)))
                ->orWhere(fn (Builder $query) => $query
                    ->whereNotNull('edge_hostname_id')
                    ->where(fn (Builder $query) => $query->whereNull('edge_status')->orWhere('edge_status', '!=', EdgeStatus::Connected))));
    }

    public function releaseEdgeHostname(): void
    {
        if ($this->edge_hostname_id !== null) {
            ReleaseEdgeHostname::dispatch($this->edge_hostname_id);
        }

        $this->forceFill(['edge_hostname_id' => null, 'edge_status' => null]);
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
            $domain->releaseEdgeHostname();
        });
    }
}

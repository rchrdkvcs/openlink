<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class ShortLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'archived_at' => 'datetime',
            'activates_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function hasPassword(): bool
    {
        return filled($this->password_hash);
    }

    protected static function booted(): void
    {
        $forget = function (ShortLink $shortLink): void {
            $keys = [];
            $domain = $shortLink->domain()->first();

            if ($domain) {
                $keys[] = "resolution:{$domain->hostname}:{$shortLink->slug}";
            }

            // When the short URL changes, the previous address may still be cached.
            // Originals are not synced yet when the saved event fires.
            $originalSlug = $shortLink->getOriginal('slug');
            $originalDomainId = $shortLink->getOriginal('domain_id');

            if ($originalSlug !== null && ($originalSlug !== $shortLink->slug || $originalDomainId !== $shortLink->domain_id)) {
                $originalDomain = $originalDomainId === $shortLink->domain_id
                    ? $domain
                    : Domain::query()->find($originalDomainId);

                if ($originalDomain) {
                    $keys[] = "resolution:{$originalDomain->hostname}:{$originalSlug}";
                }
            }

            foreach (array_unique($keys) as $key) {
                Cache::forget($key);
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }
}

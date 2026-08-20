<?php

namespace App\Models;

use App\Services\PublicSlugRegistry;
use App\Services\ShortLinks\ShortUrlCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function routingRules(): HasMany
    {
        return $this->hasMany(RoutingRule::class)->orderBy('position');
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
        $saved = function (ShortLink $shortLink): void {
            app(ShortUrlCache::class)->forgetForShortLink($shortLink);
            app(PublicSlugRegistry::class)->syncShortLink($shortLink);
        };

        static::saved($saved);
        static::deleted(function (ShortLink $shortLink): void {
            app(ShortUrlCache::class)->forgetForShortLink($shortLink);
            app(PublicSlugRegistry::class)->forget(PublicSlug::TYPE_SHORT_LINK, $shortLink->id);
        });
    }
}

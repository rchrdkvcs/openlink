<?php

namespace App\Models;

use App\Enums\AnalyticsMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrCode extends Model
{
    public const PAYLOAD_TYPES = [
        'url',
        'text',
        'email',
        'phone',
        'sms',
        'wifi',
        'vcard',
        'event',
        'location',
        'raw',
    ];

    public const STYLES = ['square', 'rounded', 'dot'];

    public const EYE_STYLES = ['square', 'rounded', 'circle'];

    public const ERROR_CORRECTIONS = ['low', 'medium', 'quartile', 'high'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'background_transparent' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function scans(): HasMany
    {
        return $this->analyticsEvents()->successful()->where('metric', AnalyticsMetric::Scan->value);
    }

    public function scopeWithScanCount(Builder $query): Builder
    {
        return $query->withCount('scans');
    }

    public function scanCount(): int
    {
        if ($this->hasDirectPayload()) {
            return 0;
        }

        return (int) ($this->scans_count ?? $this->scans()->count());
    }

    public function publicUrl(): string
    {
        if ($this->short_link_id) {
            $this->loadMissing('shortLink.domain');

            return 'https://'.$this->shortLink->domain->hostname.'/qr/'.$this->token;
        }

        return route('public.qr', $this, true);
    }

    public function encodedContent(): string
    {
        if ($this->hasDirectPayload()) {
            return (string) $this->content;
        }

        return $this->publicUrl();
    }

    public function hasDirectPayload(): bool
    {
        return ! $this->short_link_id;
    }

    public function hasLogo(): bool
    {
        return filled($this->logo_path);
    }

    protected static function booted(): void
    {
        static::creating(function (QrCode $qrCode): void {
            if (! $qrCode->workspace_id && $qrCode->short_link_id) {
                $qrCode->workspace_id = ShortLink::query()->whereKey($qrCode->short_link_id)->value('workspace_id');
            }
        });
    }
}

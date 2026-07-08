<?php

namespace App\Models;

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

    /**
     * The URL encoded in the QR image. Short Link QR Codes live on the short
     * link's domain; QR Codes with a direct payload live on the application
     * host and keep this URL stable while their content changes.
     */
    public function publicUrl(): string
    {
        if ($this->short_link_id) {
            $this->loadMissing('shortLink.domain');

            return 'https://'.$this->shortLink->domain->hostname.'/qr/'.$this->token;
        }

        return route('public.qr', $this, true);
    }

    public function hasDirectPayload(): bool
    {
        return ! $this->short_link_id;
    }

    public function hasLogo(): bool
    {
        return filled($this->logo_path);
    }
}

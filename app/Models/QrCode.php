<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrCode extends Model
{
    public const STYLES = ['square', 'rounded', 'dot'];

    public const EYE_STYLES = ['square', 'rounded', 'circle'];

    public const ERROR_CORRECTIONS = ['low', 'medium', 'quartile', 'high'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
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

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    /**
     * The URL encoded in the QR image. It lives on the short link's domain so
     * scans enter through the domain selected for the link, never through the
     * application host.
     */
    public function publicUrl(): string
    {
        $this->loadMissing('shortLink.domain');

        return 'https://'.$this->shortLink->domain->hostname.'/qr/'.$this->token;
    }

    public function hasLogo(): bool
    {
        return filled($this->logo_path);
    }
}

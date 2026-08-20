<?php

namespace App\Models;

use App\Services\PublicSlugRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BioPage extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'published' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function draftDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'draft_domain_id');
    }

    public function publishedDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'published_domain_id');
    }

    public function elements(): HasMany
    {
        return $this->hasMany(BioElement::class)->orderBy('position');
    }

    public function publishedElements(): HasMany
    {
        return $this->hasMany(BioElement::class)
            ->whereNotNull('published')
            ->orderBy('published_position');
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    protected static function booted(): void
    {
        static::saved(fn (BioPage $bioPage) => app(PublicSlugRegistry::class)->syncBioPage($bioPage));
        static::deleted(fn (BioPage $bioPage) => app(PublicSlugRegistry::class)->forget(PublicSlug::TYPE_BIO_PAGE, $bioPage->id));
    }
}

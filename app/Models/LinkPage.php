<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkPage extends Model
{
    public const PATH_PREFIX = 'p';

    public const PATH_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/';

    public const MAX_ITEMS = 50;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LinkPageItem::class)->orderBy('position')->orderBy('id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    public function hasLogo(): bool
    {
        return filled($this->logo_path);
    }

    public function publicUrl(): string
    {
        return route('public.link-page', $this->path, true);
    }
}

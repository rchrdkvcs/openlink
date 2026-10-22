<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkPage extends Model
{
    public const PATH_PREFIX = 'p';

    public const PATH_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/';

    public const ROUTE_PATTERN = '[A-Za-z0-9-]{1,64}';

    public const MAX_ITEMS = 50;

    protected $guarded = [];

    protected $attributes = [
        'show_icons' => true,
        'show_labels' => true,
        'show_urls' => false,
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'show_icons' => 'boolean',
            'show_labels' => 'boolean',
            'show_urls' => 'boolean',
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

    public function displaySettings(): array
    {
        return $this->only(['show_icons', 'show_labels', 'show_urls']);
    }
}

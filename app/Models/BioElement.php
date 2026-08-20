<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BioElement extends Model
{
    public const TYPE_DESTINATION = 'destination';

    public const TYPE_SOCIAL = 'social';

    public const TYPE_HEADING = 'heading';

    public const TYPE_TEXT = 'text';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'draft' => 'array',
            'published' => 'array',
        ];
    }

    public function bioPage(): BelongsTo
    {
        return $this->belongsTo(BioPage::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutingRule extends Model
{
    public const TYPE_CONDITIONAL = 'conditional';

    public const TYPE_SPLIT_TEST = 'split_test';

    public const MATCH_ALL = 'all';

    public const MATCH_ANY = 'any';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'conditions' => 'array',
        ];
    }

    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(RoutingVariant::class)->orderBy('position');
    }
}

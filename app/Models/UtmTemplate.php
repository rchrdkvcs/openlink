<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtmTemplate extends Model
{
    public const PARAMETERS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    protected $guarded = [];

    protected $visible = ['id', 'name', ...self::PARAMETERS];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkPageItem extends Model
{
    protected $guarded = [];

    public function linkPage(): BelongsTo
    {
        return $this->belongsTo(LinkPage::class);
    }

    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class);
    }
}

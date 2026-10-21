<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCodeTemplate extends Model
{
    public const APPEARANCE = [
        'foreground_color',
        'background_color',
        'background_transparent',
        'margin',
        'error_correction',
        'style',
        'eye_style',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'background_transparent' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function hasLogo(): bool
    {
        return filled($this->logo_path);
    }
}

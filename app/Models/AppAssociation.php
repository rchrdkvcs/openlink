<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppAssociation extends Model
{
    public const PLATFORM_IOS = 'ios';

    public const PLATFORM_ANDROID = 'android';

    public const PLATFORMS = [self::PLATFORM_IOS, self::PLATFORM_ANDROID];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sha256_cert_fingerprints' => 'array',
            'paths' => 'array',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function appId(): string
    {
        return $this->platform === self::PLATFORM_IOS
            ? $this->team_id.'.'.$this->identifier
            : $this->identifier;
    }
}

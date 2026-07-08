<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    /** Icon keys the UI maps to Lucide icons. */
    public const ICONS = [
        'briefcase',
        'rocket',
        'globe',
        'star',
        'heart',
        'zap',
        'folder',
        'users',
        'megaphone',
        'calendar',
        'shopping-bag',
        'graduation-cap',
    ];

    /** Preset color keys the UI maps to swatches. */
    public const COLORS = [
        'slate',
        'red',
        'orange',
        'amber',
        'green',
        'teal',
        'blue',
        'violet',
        'pink',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function preferredDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'preferred_domain_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class);
    }

    public function inviteLinks(): HasMany
    {
        return $this->hasMany(InviteLink::class);
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function shortLinks(): HasMany
    {
        return $this->hasMany(ShortLink::class);
    }

    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
    }
}

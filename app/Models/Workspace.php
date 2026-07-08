<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    /** Icon keys the UI maps to Lucide icons — mirrors WORKSPACE_ICON_CATEGORIES in resources/js/lib/workspaces.ts. */
    public const ICONS = [
        'briefcase',
        'building',
        'building-2',
        'landmark',
        'presentation',
        'calculator',
        'clipboard-list',
        'file-text',
        'folder',
        'archive',
        'inbox',
        'calendar',
        'clock',
        'rocket',
        'megaphone',
        'target',
        'trending-up',
        'bar-chart',
        'pie-chart',
        'sparkles',
        'flame',
        'zap',
        'star',
        'gem',
        'trophy',
        'award',
        'medal',
        'crown',
        'users',
        'user',
        'user-plus',
        'handshake',
        'heart-handshake',
        'heart',
        'smile',
        'baby',
        'store',
        'shopping-bag',
        'shopping-cart',
        'package',
        'gift',
        'credit-card',
        'wallet',
        'banknote',
        'coins',
        'piggy-bank',
        'tag',
        'ticket',
        'code',
        'terminal',
        'cpu',
        'database',
        'server',
        'cloud',
        'wifi',
        'globe',
        'monitor',
        'smartphone',
        'laptop',
        'bot',
        'gamepad-2',
        'wrench',
        'hammer',
        'settings',
        'leaf',
        'sprout',
        'flower-2',
        'tree-pine',
        'sun',
        'moon',
        'mountain',
        'waves',
        'snowflake',
        'umbrella',
        'rainbow',
        'bird',
        'cat',
        'dog',
        'fish',
        'bug',
        'paw-print',
        'coffee',
        'pizza',
        'utensils',
        'cake',
        'apple',
        'beer',
        'wine',
        'ice-cream',
        'cookie',
        'plane',
        'car',
        'bike',
        'ship',
        'map',
        'map-pin',
        'compass',
        'tent',
        'luggage',
        'graduation-cap',
        'book',
        'book-open',
        'pencil',
        'pen-tool',
        'palette',
        'brush',
        'music',
        'mic',
        'camera',
        'film',
        'clapperboard',
        'headphones',
        'puzzle',
        'lightbulb',
        'key',
        'lock',
        'shield',
        'bell',
        'bookmark',
        'flag',
        'anchor',
        'atom',
        'dumbbell',
        'home',
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

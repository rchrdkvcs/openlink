<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderPermission extends Model
{
    public const CAN_VIEW = 'can_view';
    public const CAN_EDIT = 'can_edit';
    public const CAN_MANAGE = 'can_manage';

    protected $guarded = [];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

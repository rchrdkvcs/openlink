<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstanceSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}

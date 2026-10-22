<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticObservation extends Model
{
    public const SCHEDULER = 'scheduler';

    public const WORKER = 'worker';

    public const MAIL_TEST = 'mail_test';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'name';

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'succeeded_at' => 'datetime',
        ];
    }
}

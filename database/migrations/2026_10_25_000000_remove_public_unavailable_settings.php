<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('instance_settings')->whereIn('key', [
            'public_unavailable_title',
            'public_unavailable_message',
        ])->delete();
    }

    public function down(): void {}
};

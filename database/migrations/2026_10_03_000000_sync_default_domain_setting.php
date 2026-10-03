<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hostname = DB::table('domains')->where('is_default', true)->value('hostname');

        if ($hostname === null || DB::table('instance_settings')->where('key', 'default_domain')->exists()) {
            return;
        }

        DB::table('instance_settings')->insert([
            'key' => 'default_domain',
            'value' => json_encode($hostname),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void {}
};

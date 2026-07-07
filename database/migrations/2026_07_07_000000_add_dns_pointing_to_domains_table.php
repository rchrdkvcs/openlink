<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->timestamp('dns_pointed_at')->nullable()->after('verified_at');
            $table->text('dns_check_error')->nullable()->after('failure_reason');
        });

        // Default domains serve application traffic by definition; workspace
        // domains must prove their DNS points here before becoming active.
        DB::table('domains')->where('status', 'verified')->where('is_default', true)
            ->update(['status' => 'active', 'dns_pointed_at' => now()]);
        DB::table('domains')->where('status', 'verified')
            ->update(['status' => 'ownership_verified']);
    }

    public function down(): void
    {
        DB::table('domains')->whereIn('status', ['active', 'ownership_verified'])
            ->update(['status' => 'verified']);

        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['dns_pointed_at', 'dns_check_error']);
        });
    }
};

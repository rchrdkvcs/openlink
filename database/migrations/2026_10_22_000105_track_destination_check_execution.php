<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destination_checks', function (Blueprint $table) {
            $table->string('execution_state', 16)->nullable();
            $table->uuid('generation')->nullable();
            $table->timestamp('started_at')->nullable();
        });

        DB::table('destination_checks')->whereNotNull('checked_at')->update(['execution_state' => 'completed']);
        DB::table('destination_checks')->whereNotNull('requested_at')
            ->where(fn ($query) => $query->whereNull('checked_at')->orWhereColumn('requested_at', '>', 'checked_at'))
            ->update(['execution_state' => 'queued']);
    }

    public function down(): void
    {
        Schema::table('destination_checks', function (Blueprint $table) {
            $table->dropColumn(['execution_state', 'started_at', 'generation']);
        });
    }
};

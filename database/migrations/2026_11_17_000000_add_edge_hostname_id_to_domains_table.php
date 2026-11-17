<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->string('edge_hostname_id')->nullable()->after('dns_check_error');
            $table->string('edge_status')->nullable()->after('edge_hostname_id');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn(['edge_hostname_id', 'edge_status']);
        });
    }
};

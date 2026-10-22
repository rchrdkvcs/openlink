<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->string('import_fingerprint', 64)->nullable();

            $table->unique(['workspace_id', 'import_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'import_fingerprint']);
            $table->dropColumn('import_fingerprint');
        });
    }
};

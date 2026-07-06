<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->string('style')->default('square')->after('error_correction');
            $table->string('eye_style')->default('square')->after('style');
            $table->boolean('background_transparent')->default(false)->after('eye_style');
            $table->string('logo_path')->nullable()->after('background_transparent');
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropColumn(['style', 'eye_style', 'background_transparent', 'logo_path']);
        });
    }
};

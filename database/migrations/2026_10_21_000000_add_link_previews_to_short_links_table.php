<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->string('preview_title', 120)->nullable();
            $table->string('preview_description', 300)->nullable();
            $table->string('preview_image_path')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->dropUnique(['preview_image_path']);
            $table->dropColumn(['preview_title', 'preview_description', 'preview_image_path']);
        });
    }
};

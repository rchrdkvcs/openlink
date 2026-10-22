<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->boolean('show_icons')->default(true);
            $table->boolean('show_labels')->default(true);
            $table->boolean('show_urls')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->dropColumn(['show_icons', 'show_labels', 'show_urls']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('path', 64)->unique();
            $table->string('title', 120);
            $table->string('description', 280)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });

        Schema::create('link_page_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('short_link_id')->constrained()->cascadeOnDelete();
            $table->string('label', 80)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['link_page_id', 'short_link_id']);
            $table->index(['link_page_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_page_items');
        Schema::dropIfExists('link_pages');
    }
};

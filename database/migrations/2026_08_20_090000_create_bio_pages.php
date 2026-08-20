<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bio_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('draft_domain_id')->constrained('domains')->cascadeOnDelete();
            $table->string('draft_slug', 512);
            $table->json('draft');
            $table->foreignId('published_domain_id')->nullable()->constrained('domains')->cascadeOnDelete();
            $table->string('published_slug', 512)->nullable();
            $table->json('published')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'updated_at']);
            $table->index(['published_domain_id', 'published_slug']);
        });

        Schema::create('bio_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bio_page_id')->constrained()->cascadeOnDelete();
            $table->string('client_id', 100);
            $table->unsignedTinyInteger('position')->nullable();
            $table->json('draft')->nullable();
            $table->unsignedTinyInteger('published_position')->nullable();
            $table->json('published')->nullable();
            $table->timestamps();

            $table->unique(['bio_page_id', 'client_id']);
            $table->index(['bio_page_id', 'position']);
            $table->index(['bio_page_id', 'published_position']);
        });

        Schema::create('public_slugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 512);
            $table->foreignId('short_link_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('bio_page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['domain_id', 'slug']);
        });

        DB::table('short_links')->orderBy('id')->each(function (object $shortLink): void {
            DB::table('public_slugs')->insert([
                'domain_id' => $shortLink->domain_id,
                'slug' => $shortLink->slug,
                'short_link_id' => $shortLink->id,
                'bio_page_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_slugs');
        Schema::dropIfExists('bio_elements');
        Schema::dropIfExists('bio_pages');
    }
};

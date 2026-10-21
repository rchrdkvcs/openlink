<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(0)->after('password_hash');
        });

        Schema::create('short_link_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('short_link_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('action', 16);
            $table->string('channel', 16);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('restored_from')->nullable();
            $table->unsignedSmallInteger('schema_version');
            $table->json('snapshot');
            $table->json('changes');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['short_link_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('short_link_revisions');

        Schema::table('short_links', function (Blueprint $table) {
            $table->dropColumn('revision');
        });
    }
};

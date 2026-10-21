<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->char('url_hash', 64);
            $table->string('status', 32)->default('unchecked');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('detail')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('failing_since')->nullable();
            $table->timestamp('incident_opened_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'url_hash']);
            $table->index('checked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_checks');
    }
};

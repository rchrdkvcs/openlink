<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->json('filters');
            $table->timestamps();

            $table->index(['workspace_id', 'user_id']);
        });

        Schema::create('analytics_summary_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('timezone', 64)->default('UTC');
            $table->timestamps();
        });

        Schema::create('analytics_summary_recipients', function (Blueprint $table) {
            $table->foreignId('analytics_summary_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_member_id')->constrained()->cascadeOnDelete();

            $table->primary(['analytics_summary_schedule_id', 'workspace_member_id']);
        });

        Schema::create('analytics_summary_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analytics_summary_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('period_date');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure')->nullable();
            $table->timestamps();

            $table->unique(['analytics_summary_schedule_id', 'user_id', 'period_date'], 'analytics_summary_deliveries_period_unique');
            $table->index(['analytics_summary_schedule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_summary_deliveries');
        Schema::dropIfExists('analytics_summary_recipients');
        Schema::dropIfExists('analytics_summary_schedules');
        Schema::dropIfExists('analytics_views');
    }
};

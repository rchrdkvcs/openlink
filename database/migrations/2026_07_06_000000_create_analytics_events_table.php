<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('short_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('domain_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->string('metric', 8);
            $table->string('outcome', 32);
            $table->boolean('is_bot')->default(false);
            $table->string('visitor_hash', 32)->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('referrer_channel', 16)->nullable();
            $table->char('country', 2)->nullable();
            $table->char('language', 2)->nullable();
            $table->string('device_type', 16)->nullable();
            $table->string('browser', 32)->nullable();
            $table->string('os', 32)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'occurred_at']);
            $table->index(['workspace_id', 'metric', 'outcome', 'occurred_at'], 'analytics_events_metric_idx');
            $table->index(['short_link_id', 'occurred_at']);
            $table->index(['qr_code_id', 'occurred_at']);
        });

        // The aggregate tables never populated reliably in production (their
        // writer depended on a queue worker); the raw event table replaces them.
        Schema::dropIfExists('analytics_totals');
        Schema::dropIfExists('analytics_daily_aggregates');
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');

        Schema::create('analytics_daily_aggregates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('short_link_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('metric');
            $table->string('outcome');
            $table->string('referrer_host')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('device_type')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->index(['workspace_id', 'date']);
            $table->index(['short_link_id', 'date']);
            $table->index(['qr_code_id', 'date']);
        });

        Schema::create('analytics_totals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('short_link_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('metric');
            $table->string('outcome')->nullable();
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'short_link_id', 'qr_code_id', 'metric', 'outcome'], 'analytics_totals_unique');
        });
    }
};

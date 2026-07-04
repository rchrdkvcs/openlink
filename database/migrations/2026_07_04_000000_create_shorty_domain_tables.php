<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instance_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('preferred_domain_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'email']);
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('hostname')->unique();
            $table->string('status')->default('pending_verification');
            $table->string('verification_token')->unique();
            $table->boolean('is_default')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->foreign('preferred_domain_id')->references('id')->on('domains')->nullOnDelete();
        });

        Schema::create('folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('folder_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission');
            $table->timestamps();

            $table->unique(['folder_id', 'user_id']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('slug', 512);
            $table->text('destination_url');
            $table->text('fallback_url')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('activates_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('visit_limit')->nullable();
            $table->unsignedInteger('successful_visits')->default(0);
            $table->string('password_hash')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'slug']);
            $table->index(['workspace_id', 'folder_id']);
        });

        Schema::create('short_link_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['short_link_id', 'tag_id']);
        });

        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_link_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('token')->unique();
            $table->unsignedInteger('size')->default(1024);
            $table->string('foreground_color', 7)->default('#111827');
            $table->string('background_color', 7)->default('#ffffff');
            $table->unsignedTinyInteger('margin')->default(2);
            $table->string('error_correction')->default('medium');
            $table->timestamps();
        });

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

    public function down(): void
    {
        Schema::dropIfExists('analytics_totals');
        Schema::dropIfExists('analytics_daily_aggregates');
        Schema::dropIfExists('qr_codes');
        Schema::dropIfExists('short_link_tag');
        Schema::dropIfExists('short_links');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('folder_permissions');
        Schema::dropIfExists('folders');
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropForeign(['preferred_domain_id']);
        });
        Schema::dropIfExists('domains');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('instance_settings');
    }
};

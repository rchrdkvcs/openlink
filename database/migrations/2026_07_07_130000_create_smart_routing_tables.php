<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('short_link_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32)->default('conditional');
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_enabled')->default(true);
            $table->string('match_mode', 8)->default('all');
            $table->unsignedTinyInteger('conditions_version')->default(1);
            $table->json('conditions')->nullable();
            $table->text('destination_url')->nullable();
            $table->timestamps();

            $table->index(['short_link_id', 'position']);
        });

        Schema::create('routing_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_rule_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_enabled')->default(true);
            $table->text('destination_url');
            $table->unsignedInteger('weight')->default(50);
            $table->timestamps();

            $table->index(['routing_rule_id', 'position']);
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->foreignId('routing_rule_id')->nullable()->after('domain_id')->constrained('routing_rules')->nullOnDelete();
            $table->foreignId('routing_variant_id')->nullable()->after('routing_rule_id')->constrained('routing_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropForeign(['routing_variant_id']);
            $table->dropForeign(['routing_rule_id']);
            $table->dropColumn(['routing_variant_id', 'routing_rule_id']);
        });

        Schema::dropIfExists('routing_variants');
        Schema::dropIfExists('routing_rules');
    }
};

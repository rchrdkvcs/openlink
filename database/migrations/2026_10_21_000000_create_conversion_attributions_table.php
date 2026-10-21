<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('short_links', function (Blueprint $table) {
            $table->string('conversion_goal', 80)->nullable();
        });

        Schema::create('conversion_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('short_link_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qr_code_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('routing_rule_id')->nullable()->constrained('routing_rules')->nullOnDelete();
            $table->foreignId('routing_variant_id')->nullable()->constrained('routing_variants')->nullOnDelete();
            $table->string('metric', 8);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('converted_at')->nullable();

            $table->index(['workspace_id', 'issued_at']);
            $table->index(['short_link_id', 'issued_at']);
            $table->index(['converted_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversion_attributions');

        Schema::table('short_links', function (Blueprint $table) {
            $table->dropColumn('conversion_goal');
        });
    }
};

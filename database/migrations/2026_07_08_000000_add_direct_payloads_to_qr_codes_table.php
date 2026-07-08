<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('payload_type')->nullable()->after('token');
            $table->json('payload')->nullable()->after('payload_type');
            $table->text('content')->nullable()->after('payload');
            $table->foreignId('short_link_id')->nullable()->change();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'created_at']);
            $table->dropConstrainedForeignId('workspace_id');
            $table->dropColumn(['payload_type', 'payload', 'content']);
            $table->foreignId('short_link_id')->nullable(false)->change();
        });
    }
};

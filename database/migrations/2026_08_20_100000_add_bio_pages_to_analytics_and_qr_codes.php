<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->foreignId('bio_page_id')->nullable()->after('short_link_id')->constrained()->cascadeOnDelete();
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            $table->foreignId('bio_page_id')->nullable()->after('qr_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bio_element_id')->nullable()->after('bio_page_id')->constrained()->nullOnDelete();
            $table->string('metric', 32)->change();

            $table->index(['bio_page_id', 'occurred_at']);
            $table->index(['bio_page_id', 'bio_element_id', 'metric', 'occurred_at'], 'analytics_events_bio_idx');
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['bio_page_id', 'occurred_at']);
            $table->dropIndex('analytics_events_bio_idx');
            $table->dropConstrainedForeignId('bio_element_id');
            $table->dropConstrainedForeignId('bio_page_id');
            $table->string('metric', 8)->change();
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bio_page_id');
        });
    }
};

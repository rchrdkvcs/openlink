<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE qr_codes SET workspace_id = short_links.workspace_id FROM short_links WHERE qr_codes.short_link_id = short_links.id AND qr_codes.workspace_id IS NULL');

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable(false)->change();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE qr_codes ADD CONSTRAINT qr_codes_target_check CHECK (
                (short_link_id IS NOT NULL AND payload_type IS NULL AND payload IS NULL AND content IS NULL)
                OR
                (short_link_id IS NULL AND payload_type IS NOT NULL AND payload IS NOT NULL AND content IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE qr_codes DROP CONSTRAINT IF EXISTS qr_codes_target_check');

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->change();
        });
    }
};

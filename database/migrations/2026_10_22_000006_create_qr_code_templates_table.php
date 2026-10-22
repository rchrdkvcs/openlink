<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_code_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('foreground_color', 7);
            $table->string('background_color', 7);
            $table->boolean('background_transparent')->default(false);
            $table->unsignedTinyInteger('margin');
            $table->string('error_correction');
            $table->string('style');
            $table->string('eye_style');
            $table->string('logo_path')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('qr_code_templates')) {
            Storage::delete(DB::table('qr_code_templates')->whereNotNull('logo_path')->pluck('logo_path')->all());
        }

        Schema::dropIfExists('qr_code_templates');
    }
};

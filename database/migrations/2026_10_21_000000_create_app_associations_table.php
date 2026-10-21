<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);
            $table->string('team_id', 10)->nullable();
            $table->string('identifier');
            $table->json('sha256_cert_fingerprints')->nullable();
            $table->json('paths')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'platform', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_associations');
    }
};

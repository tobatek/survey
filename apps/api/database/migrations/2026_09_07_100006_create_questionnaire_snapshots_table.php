<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §7.6/§16.3 — snapshot immutable dibuat sekali saat draft->ready
// (FinalizeQuestionnaireAction). Campaign (Fase 3) akan merujuk snapshot ini,
// bukan questionnaire yang masih bisa berubah strukturnya (jika return-to-draft
// lalu diedit lagi -> versi baru).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('questionnaire_id')->constrained('questionnaires')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('structure_json');
            $table->string('checksum');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['questionnaire_id', 'version']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_snapshots');
    }
};

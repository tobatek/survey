<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tipe MVP (FSD §7.3): short_text, long_text, single_choice, multiple_choice,
// dropdown, likert, matrix, number, date. `validation_json` menampung aturan
// per tipe (mis. min/max untuk number, format untuk date). `analytics_config_json`
// dipakai Fase 5 (Hasil Analisa), disiapkan kolomnya sekarang saja.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('questionnaire_id')->constrained('questionnaires')->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained('questionnaire_sections')->cascadeOnDelete();
            $table->string('code');
            $table->string('type');
            $table->text('text');
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('allow_not_applicable')->default(false);
            $table->json('validation_json')->nullable();
            $table->json('analytics_config_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['questionnaire_id', 'code']);
            $table->index(['section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

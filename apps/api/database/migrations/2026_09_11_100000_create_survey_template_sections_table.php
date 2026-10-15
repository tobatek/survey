<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §15 — struktur anak SurveyTemplate, diisi lewat Import Template
// (JSON slice ini). Meniru pola questionnaire_sections.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_template_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('survey_templates')->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['template_id', 'code']);
            $table->index(['template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_template_sections');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Meniru pola questions. `validation_json` menampung min_value/max_value,
// `analytics_config_json` menampung chart_type — sama seperti Question.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_template_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('template_id')->constrained('survey_templates')->cascadeOnDelete();
            $table->foreignUuid('section_id')->constrained('survey_template_sections')->cascadeOnDelete();
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

            $table->unique(['template_id', 'code']);
            $table->index(['section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_template_questions');
    }
};

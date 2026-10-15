<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaire_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('questionnaire_id')->constrained('questionnaires')->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['questionnaire_id', 'code']);
            $table->index(['questionnaire_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_sections');
    }
};

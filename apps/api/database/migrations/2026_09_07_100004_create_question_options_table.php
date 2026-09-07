<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// `target_section_id` = branching section-level (FSD §7.4): memilih opsi ini
// mengarahkan responden ke section tujuan, bukan urutan default berikutnya.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('code');
            $table->string('label');
            $table->decimal('numeric_score', 8, 2)->nullable();
            $table->boolean('is_positive')->default(true);
            $table->boolean('is_not_applicable')->default(false);
            $table->foreignUuid('target_section_id')->nullable()->constrained('questionnaire_sections')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['question_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};

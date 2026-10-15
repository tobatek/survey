<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §16.5 — pilihan yang dipilih untuk jawaban single/multiple choice
// atau baris matrix; child murni dari answers, tanpa timestamps sendiri.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_choices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('answer_id')->constrained('answers')->cascadeOnDelete();
            $table->string('option_code');
            $table->decimal('numeric_score', 8, 4)->nullable();

            $table->unique(['answer_id', 'option_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_choices');
    }
};

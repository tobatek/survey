<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §16.5 — `snapshot_question_code` merujuk kode pertanyaan di dalam
// structure_json snapshot campaign, bukan question_id live, karena snapshot
// bersifat immutable sejak campaign diaktifkan.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('response_id')->constrained('responses')->cascadeOnDelete();
            $table->string('snapshot_question_code');
            $table->string('matrix_row_code')->nullable();
            $table->string('value_type');
            $table->text('text_value')->nullable();
            $table->decimal('numeric_value', 12, 4)->nullable();
            $table->date('date_value')->nullable();
            $table->boolean('is_not_applicable')->default(false);
            $table->timestamp('created_at');

            $table->index(['response_id', 'snapshot_question_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};

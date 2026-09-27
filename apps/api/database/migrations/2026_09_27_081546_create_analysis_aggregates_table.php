<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §16.6 -- hasil agregasi per campaign/pertanyaan/opsi, dipakai Fase 5
// slice 4 (materialized result untuk campaign besar). Belum ditulis oleh
// action manapun di slice ini (murni skema, mengikuti pola idempotency_keys
// di Fase 4 slice 1 yang juga dibuat sebelum dipakai). Composite unique key
// mencegah baris duplikat untuk kombinasi scope+filter+pertanyaan yang sama.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_aggregates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('snapshot_checksum');
            $table->string('normalized_filter_hash');
            $table->string('question_code');
            $table->string('option_code')->nullable();
            $table->string('matrix_row_code')->nullable();
            $table->unsignedBigInteger('numerator')->default(0);
            $table->unsignedBigInteger('denominator')->default(0);
            $table->decimal('sum', 20, 4)->nullable();
            $table->unsignedBigInteger('count')->default(0);
            $table->decimal('min', 20, 4)->nullable();
            $table->decimal('max', 20, 4)->nullable();
            $table->json('payload_json')->nullable();
            $table->timestamp('refreshed_at');

            $table->unique(
                ['campaign_id', 'snapshot_checksum', 'normalized_filter_hash', 'question_code', 'option_code', 'matrix_row_code'],
                'analysis_aggregates_scope_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_aggregates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §10 (campaign) & §16.4 (skema). `snapshot_id` diisi saat aktivasi,
// menunjuk snapshot terakhir dari questionnaire (dibuat saat finalize, lihat
// questionnaire_snapshots) — campaign tidak membuat snapshot baru sendiri.
// Tidak ada lock_version: transisi status campaign pakai conditional update
// (WHERE status = expected), bukan optimistic lock ala builder Kuesioner,
// karena wizard campaign diedit sekuensial oleh satu admin.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->foreignUuid('questionnaire_id')->constrained('questionnaires')->cascadeOnDelete();
            $table->foreignUuid('snapshot_id')->nullable()->constrained('questionnaire_snapshots')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->json('target_rule_json')->nullable();
            $table->unsignedInteger('denominator_count')->nullable();
            $table->unsignedInteger('minimum_segment_size')->default(5);
            $table->foreignUuid('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('starts_at');
            $table->index('ends_at');
            $table->index('questionnaire_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §7 (builder) & §16.3 (skema) — `lock_version` dipakai untuk optimistic
// locking (header If-Match) pada semua mutasi builder; belum ada pola ini di
// Fase 1, didesain baru di sini. `snapshot_checksum` diisi saat finalize
// (draft->ready), lihat questionnaire_snapshots.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaires', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('audience_type');
            $table->string('identity_mode');
            $table->uuid('academic_period_id')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('allow_draft_save')->default(true);
            $table->text('privacy_notice')->nullable();
            $table->text('confirmation_text')->nullable();
            $table->unsignedInteger('structure_version')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->string('snapshot_checksum')->nullable();
            $table->foreignUuid('template_id')->nullable()->constrained('survey_templates')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'audience_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaires');
    }
};

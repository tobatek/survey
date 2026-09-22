<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §11.4 & §16.5 — sesi pengisian survei yang masih berjalan (draft).
// `eligibility_id`/`subject_type`/`subject_id` HANYA diisi mode identified;
// mode anonymous sengaja tidak menyimpan referensi identitas apa pun (tanpa
// FK constraint ke identitas, meniru pola audience_members.subject_id di
// Fase 3) — dipakai `anonymous_nonce_hash` sebagai gantinya. Proyek ini
// belum pernah pakai skema Postgres selain public (lihat migrasi Campaign),
// jadi tabel ini tetap di public juga, bukan skema `response_vault` literal
// FSD — privasi dijaga lewat desain kolom, bukan pemisahan skema fisik.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('response_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUuid('snapshot_id')->constrained('questionnaire_snapshots')->cascadeOnDelete();
            $table->foreignUuid('eligibility_id')->nullable()->constrained('eligibilities')->nullOnDelete();
            $table->string('mode');
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('anonymous_nonce_hash')->nullable()->unique();
            $table->json('segment_snapshot_json');
            $table->string('status')->default('draft');
            $table->json('draft_answers_json')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('tenant_id')->default('default');
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index('expires_at');
            $table->index(['campaign_id', 'eligibility_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_sessions');
    }
};

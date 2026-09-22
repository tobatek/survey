<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §11.4 & §16.5 — hasil final tersubmit, immutable (tidak ada update
// setelah insert, hanya `created_at` — sama pola questionnaire_snapshots).
// `subject_type`/`subject_id` tanpa FK constraint, hanya diisi mode
// identified; mode anonymous sama sekali tidak menyimpan referensi
// identitas, cuma `segment_snapshot_json` (atribut kategorikal allowlist).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUuid('snapshot_id')->constrained('questionnaire_snapshots')->cascadeOnDelete();
            $table->foreignUuid('response_session_id')->nullable()->constrained('response_sessions')->nullOnDelete();
            $table->string('mode');
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->json('segment_snapshot_json');
            $table->string('submission_nonce_hash')->unique();
            $table->timestamp('submitted_at');
            $table->unsignedInteger('response_duration_seconds')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->string('tenant_id')->default('default');
            $table->timestamp('created_at');

            $table->index(['campaign_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};

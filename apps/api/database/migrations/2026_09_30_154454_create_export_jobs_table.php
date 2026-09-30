<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §16.6 (skema) & §14.2 (alur async export). Fase 5 slice 5 -- hanya
// format xlsx (CSV/PDF/PNG di slice 6, kolom `format` sengaja string bebas
// bukan enum supaya tidak perlu migration baru tiap tambah format).
// `tenant_id` eksplisit (bukan FK/global scope), konsisten pola `campaigns`.
// `expires_at` dipakai DUA tujuan: TTL unduhan file setelah sukses (diisi
// ulang saat status jadi succeeded), DAN fallback TTL "proses macet" untuk
// job yang tidak pernah mencapai status terminal (diisi saat create) --
// lihat GenerateAnalyticsExportJob/PruneExpiredExports. Tidak ada state
// `expired` terpisah -- pola cleanup ikut PruneExpiredTemplateImports apa
// adanya (hard-delete file+row lewat expires_at, semua status), sesuai
// keputusan scope slice 5 (tidak ada kebutuhan audit trail jangka panjang
// yang disebut FSD untuk export_jobs). Sheet `Comments`/`Raw Data` (FSD
// §14.1) sengaja belum dibangun -- komentar belum ada (slice 6), raw data
// di luar backlog slice 5. Notifikasi in-app (§14.2 langkah 6) juga belum
// ada di codebase ini -- status export hanya lewat polling GET /api/exports/{id}.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('format')->default('xlsx');
            $table->json('filter_json')->nullable();
            $table->string('filter_hash');
            $table->boolean('include_comments')->default(false);
            $table->string('status')->default('queued');
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'campaign_id']);
            $table->index(['requested_by', 'campaign_id', 'filter_hash', 'format', 'status'], 'export_jobs_dedup_lookup');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};

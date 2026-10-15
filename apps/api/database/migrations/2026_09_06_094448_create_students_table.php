<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cache read-only dari Portal Master Data API (GET /api/master/students —
// docs/15_SSO_PORTAL_VALIDASI_SEPARATION_PLAN.md Tahap 0). Prodi/fakultas
// disimpan sebagai kode+nama flat (bukan FK ke tabel lokal) karena Portal —
// bukan aplikasi ini — adalah pemilik data referensi tsb; ini adaptasi dari
// skema FSD §16.2 yang aslinya mengasumsikan sync langsung dari SIAKAD.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->string('source_system')->default('portal');
            $table->string('source_id');
            $table->string('nim');
            $table->string('normalized_nim');
            $table->string('name');
            $table->string('normalized_name');
            $table->string('institutional_email')->nullable();
            $table->string('faculty_name')->nullable();
            $table->string('program_study_code')->nullable();
            $table->string('program_study_name')->nullable();
            $table->string('degree_level')->nullable();
            $table->unsignedSmallInteger('cohort_year')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source_system', 'source_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'program_study_code', 'cohort_year']);
            $table->index(['tenant_id', 'normalized_nim']);
            $table->index(['tenant_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

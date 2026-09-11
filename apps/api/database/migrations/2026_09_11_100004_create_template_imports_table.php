<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §15.4 — staging record hasil validasi sebelum commit. `raw_payload`
// menyimpan JSON asli yang disubmit (retensi "file sumber" untuk slice JSON
// ini). Baris kedaluwarsa (`expires_at` lewat) tidak boleh dikonfirmasi lagi.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->string('filename')->nullable();
            $table->json('raw_payload');
            $table->string('status')->default('pending_validation');
            $table->json('errors_json')->nullable();
            $table->json('warnings_json')->nullable();
            $table->json('preview_json')->nullable();
            $table->string('checksum')->nullable();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->foreignUuid('committed_template_id')->nullable()->constrained('survey_templates')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_imports');
    }
};

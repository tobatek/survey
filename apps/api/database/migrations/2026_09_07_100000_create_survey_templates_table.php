<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §16.3 — template kuesioner reusable (dipakai saat membuat questionnaire
// baru "dari template"). Pipeline import XLSX/JSON (§15) yang mengisi tabel
// ini secara massal belum dibangun di slice ini — kolom `source_import_id`
// disiapkan untuk itu.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->string('code');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('audience_type');
            $table->string('identity_mode');
            $table->string('language')->default('id');
            $table->unsignedInteger('schema_version')->default(1);
            $table->string('status')->default('active');
            $table->uuid('source_import_id')->nullable();
            $table->string('checksum')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_templates');
    }
};

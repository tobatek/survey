<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §16.1 `user_scopes` — pemeriksaan otorisasi bukan hanya role, tapi juga
// scope organisasi (mis. operator dibatasi ke prodi/unit tertentu). Kosong
// pada Fase 1 (belum ada UI pengelolaan scope); kolom disiapkan agar Policy
// di Fase 2+ tidak perlu migrasi ulang.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_scopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type');
            $table->string('scope_id');
            $table->timestamps();

            $table->unique(['user_id', 'scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_scopes');
    }
};

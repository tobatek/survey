<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §10.2 & §16.4. `subject_id` sengaja bukan foreign key ber-constraint:
// ia menunjuk salah satu dari dua tabel berbeda (students/employees)
// tergantung `subject_type`, jadi resolusi relasinya manual di model.
// `attributes_snapshot_json` HANYA berisi atribut kategorikal (prodi,
// angkatan, dst) — bukan identitas langsung (nama/NIM/email), sesuai
// catatan privasi FSD §10.2.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audience_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('subject_type');
            $table->uuid('subject_id');
            $table->json('attributes_snapshot_json');
            $table->string('snapshot_hash');
            $table->timestamps();

            $table->unique(['campaign_id', 'subject_type', 'subject_id']);
            $table->index(['campaign_id', 'subject_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_members');
    }
};

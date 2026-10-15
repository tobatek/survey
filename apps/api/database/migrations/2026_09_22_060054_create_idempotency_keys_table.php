<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §11.4 — mencegah submit ganda kalau client mengulang request dengan
// header Idempotency-Key yang sama (mis. retry jaringan). `response_reference`
// diisi setelah submit sukses supaya pengulangan key yang sama bisa langsung
// mengembalikan hasil yang sama tanpa insert ulang.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('scope_hash');
            $table->string('key_hash');
            $table->string('request_hash');
            $table->string('status')->default('processing');
            $table->foreignUuid('response_reference')->nullable()->constrained('responses')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('created_at');

            $table->unique(['scope_hash', 'key_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};

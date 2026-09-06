<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Skema identity mengikuti FSD §16.1. Tidak ada login lokal (email/password) —
// satu-satunya jalur masuk adalah SSO Portal (docs/15), sehingga tidak ada
// kolom password/remember_token maupun tabel password_reset_tokens.
// Session disimpan di Redis (SESSION_DRIVER=redis), bukan tabel `sessions`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->string('hub_user_id')->nullable();
            $table->string('email');
            $table->string('display_name');
            $table->string('status')->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'hub_user_id']);
            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

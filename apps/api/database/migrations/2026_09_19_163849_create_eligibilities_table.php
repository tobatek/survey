<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §10.3 & §16.4 — status: eligible/invited/opened/started/completed/
// expired/excluded. Satu eligibility unik per (campaign, audience_member),
// dijaga unique constraint agar aktivasi ulang tetap idempotent.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eligibilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUuid('audience_member_id')->constrained('audience_members')->cascadeOnDelete();
            $table->string('status')->default('eligible');
            $table->string('invitation_token_hash')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('completion_receipt_hash')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'audience_member_id']);
            $table->index(['campaign_id', 'status']);
            $table->index('invitation_token_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eligibilities');
    }
};

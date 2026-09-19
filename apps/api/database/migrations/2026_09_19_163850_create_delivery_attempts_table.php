<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §10.3 & §16.4 — jejak percobaan pengiriman undangan/reminder per
// eligibility. `provider_message_id` unique (nullable-safe: banyak baris
// NULL diizinkan) untuk mencegah pencatatan ganda dari webhook provider.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('eligibility_id')->constrained('eligibilities')->cascadeOnDelete();
            $table->string('channel');
            $table->string('status');
            $table->string('provider_message_id')->nullable()->unique();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error_code')->nullable();
            $table->string('sanitized_error')->nullable();
            $table->timestamps();

            $table->index('eligibility_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_attempts');
    }
};

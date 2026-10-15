<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §22 — audit log wajib untuk login, sync, dan aksi sensitif lain.
// Payload disanitasi (tidak memuat jawaban responden atau token) di level
// Action (RecordAuditEventAction), bukan di migrasi ini.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->string('resource_id')->nullable();
            $table->string('result');
            $table->json('context')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('occurred_at');

            $table->index(['tenant_id', 'action']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};

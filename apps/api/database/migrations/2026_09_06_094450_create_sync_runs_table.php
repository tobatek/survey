<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// FSD §16.2 sync_runs/sync_errors + §8.3 alur sync. RULE-D02: sync gagal
// tidak menonaktifkan seluruh master — status per-run disimpan di sini,
// bukan menghapus/menonaktifkan data students/employees secara massal.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->string('entity_type'); // student | employee
            $table->string('mode'); // scheduled | manual
            $table->string('status')->default('running'); // running|success|partial|failed
            $table->foreignUuid('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('upserted_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->uuid('correlation_id');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'entity_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};

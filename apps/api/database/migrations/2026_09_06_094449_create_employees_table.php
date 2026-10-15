<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cache read-only dari Portal Master Data API (GET /api/master/employees).
// Lihat catatan skema di create_students_table.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->default('default');
            $table->string('source_system')->default('portal');
            $table->string('source_id');
            $table->string('employee_number');
            $table->string('name');
            $table->string('normalized_name');
            $table->string('institutional_email')->nullable();
            $table->string('employee_type')->nullable();
            $table->string('work_unit_name')->nullable();
            $table->string('position_name')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source_system', 'source_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'employee_type', 'work_unit_name']);
            $table->index(['tenant_id', 'employee_number']);
            $table->index(['tenant_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};

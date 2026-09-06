<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sync_run_id')->constrained()->cascadeOnDelete();
            $table->string('source_id')->nullable();
            $table->string('error_code');
            $table->text('message');
            $table->json('rejected_payload')->nullable();
            $table->timestamps();

            $table->index('sync_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_errors');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Branding institusi (logo, nama, warna, teks login). Satu baris per tenant,
// diunggah manual oleh admin -- bukan dari Portal (Portal belum punya
// endpoint branding). File disimpan di disk `public` (storage/app/public).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branding_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->unique();
            $table->string('institution_name')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('login_title')->nullable();
            $table->text('login_description')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branding_settings');
    }
};

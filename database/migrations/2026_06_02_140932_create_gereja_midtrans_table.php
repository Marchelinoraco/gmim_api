<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gereja_midtrans', function (Blueprint $table) {
            $table->string('gereja_id')->primary();
            $table->text('server_key');          // Dienkripsi Laravel Crypt — tidak pernah dikirim ke FE
            $table->string('client_key');        // Boleh dikirim ke FE (untuk Snap.js)
            $table->string('merchant_id')->nullable();
            $table->boolean('is_production')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gereja_midtrans');
    }
};

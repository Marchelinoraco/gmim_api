<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Katalog paket langganan
        Schema::create('paket', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('nama');
            $table->unsignedBigInteger('harga_bulanan')->default(0);
            $table->unsignedBigInteger('harga_tahunan')->default(0);
            $table->json('batas')->nullable();         // {max_pengguna: 5, fitur: ["aset","gaji"]}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Langganan per gereja (1-1 dengan gereja)
        Schema::create('langganan', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('paket_id');
            $table->string('status')->default('trial');     // trial|active|past_due|expired|canceled
            $table->string('siklus')->default('bulanan');   // bulanan|tahunan
            $table->date('trial_berakhir')->nullable();
            $table->date('mulai')->nullable();
            $table->date('berakhir')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->unique('gereja_id');
        });

        // Tagihan / invoice
        Schema::create('tagihan', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('langganan_id');
            $table->string('nomor')->unique();              // INV-2026-0001
            $table->string('periode');                      // "2026-06"
            $table->unsignedBigInteger('jumlah');
            $table->string('status')->default('unpaid');    // unpaid|paid|void
            $table->date('jatuh_tempo');
            $table->timestamp('dibayar_pada')->nullable();
            $table->string('midtrans_order_id')->nullable();
            $table->string('snap_token')->nullable();
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->index(['gereja_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
        Schema::dropIfExists('langganan');
        Schema::dropIfExists('paket');
    }
};

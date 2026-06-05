<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('nama');
            $table->string('jabatan')->nullable();
            $table->string('tipe')->default('honor');         // gaji_tetap|honor
            $table->unsignedBigInteger('nominal_default')->default(0);
            $table->string('no_rekening')->nullable();
            $table->string('bank')->nullable();
            $table->string('status')->default('active');      // active|nonaktif
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->index('gereja_id');
        });

        Schema::create('pembayaran_gaji', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('pegawai_id');
            $table->string('periode');                        // "2026-06"
            $table->date('tanggal_bayar')->nullable();
            $table->unsignedBigInteger('nominal');
            $table->string('status')->default('pending');     // pending|dibayar
            $table->string('pengeluaran_id')->nullable();     // FK ke pengeluaran (auto-buat saat dibayar)
            $table->string('input_by')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->foreign('pegawai_id')->references('id')->on('pegawai')->restrictOnDelete();
            $table->index(['gereja_id', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran_gaji');
        Schema::dropIfExists('pegawai');
    }
};

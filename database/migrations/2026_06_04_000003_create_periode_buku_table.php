<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_buku', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('tipe');                // bulanan | tahunan
            $table->string('periode');             // "2026-06" (bulanan) | "2026" (tahunan)
            $table->unsignedBigInteger('saldo_awal')->default(0);
            $table->unsignedBigInteger('total_pemasukan')->default(0);
            $table->unsignedBigInteger('total_pengeluaran')->default(0);
            $table->unsignedBigInteger('saldo_akhir')->default(0);
            $table->string('status')->default('open');  // open | closed
            $table->string('closed_by')->nullable();     // church_users.id
            $table->timestamp('closed_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->unique(['gereja_id', 'tipe', 'periode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_buku');
    }
};

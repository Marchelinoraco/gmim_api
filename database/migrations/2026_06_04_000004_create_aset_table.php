<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aset', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('kode')->nullable();
            $table->string('nama');
            $table->string('kategori');         // tanah|bangunan|kendaraan|alat_musik|elektronik|inventaris|lainnya
            $table->date('tanggal_perolehan')->nullable();
            $table->unsignedBigInteger('nilai_perolehan')->default(0);
            $table->string('lokasi')->nullable();
            $table->string('kondisi')->default('baik'); // baik|rusak_ringan|rusak_berat|dihapus
            $table->string('bukti_gambar')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->index(['gereja_id', 'kategori']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gereja', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->timestamps();
        });

        Schema::create('church_users', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('nama');
            $table->string('role');
            $table->string('username');
            $table->string('password');
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->unique(['gereja_id', 'username']);
        });

        Schema::create('kategori_persembahan', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('nama');
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->unique(['gereja_id', 'nama']);
        });

        Schema::create('nama_persembahan', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('kategori_persembahan_id');
            $table->string('nama');
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->foreign('kategori_persembahan_id')->references('id')->on('kategori_persembahan')->cascadeOnDelete();
        });

        Schema::create('pemasukan', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->date('tanggal');
            $table->string('kategori_persembahan_id');
            $table->string('nama_persembahan_id');
            $table->unsignedBigInteger('jumlah');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->foreign('kategori_persembahan_id')->references('id')->on('kategori_persembahan')->restrictOnDelete();
            $table->foreign('nama_persembahan_id')->references('id')->on('nama_persembahan')->restrictOnDelete();
            $table->index(['gereja_id', 'tanggal']);
        });

        Schema::create('kategori_pengeluaran', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('nama');
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->unique(['gereja_id', 'nama']);
        });

        Schema::create('pengeluaran', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->date('tanggal');
            $table->string('kategori_pengeluaran_id');
            $table->unsignedBigInteger('jumlah');
            $table->text('keterangan');
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->foreign('kategori_pengeluaran_id')->references('id')->on('kategori_pengeluaran')->restrictOnDelete();
            $table->index(['gereja_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengeluaran');
        Schema::dropIfExists('kategori_pengeluaran');
        Schema::dropIfExists('pemasukan');
        Schema::dropIfExists('nama_persembahan');
        Schema::dropIfExists('kategori_persembahan');
        Schema::dropIfExists('church_users');
        Schema::dropIfExists('gereja');
    }
};

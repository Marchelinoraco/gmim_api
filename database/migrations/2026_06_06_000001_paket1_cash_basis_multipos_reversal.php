<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 1 — F-1 Cash Basis + F-5 Multi-Pos Kas + F-6 Reversal.
 * Satu migrasi gabungan, di belakang feature flag multi_pos_kas.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Feature flag per gereja ──────────────────────────────────────────
        Schema::table('gereja', function (Blueprint $table) {
            $table->boolean('multi_pos_kas')->default(true)->after('status_langganan');
        });

        // ── Tabel pos_kas (F-5) ──────────────────────────────────────────────
        Schema::create('pos_kas', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->string('nama');                          // "Tunai", "Rekening BSG", "Midtrans"
            $table->enum('tipe', ['tunai', 'bank', 'midtrans']);
            $table->string('nama_bank')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->bigInteger('saldo_awal')->default(0);    // saldo saat mulai pakai sistem
            $table->boolean('is_aktif')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->index(['gereja_id', 'is_aktif']);
        });

        // ── Tabel mutasi_kas (F-5 transfer antar pos) ────────────────────────
        Schema::create('mutasi_kas', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id');
            $table->date('tanggal');
            $table->string('pos_asal_id');
            $table->string('pos_tujuan_id');
            $table->bigInteger('jumlah');
            $table->bigInteger('biaya_admin')->default(0);
            $table->text('keterangan')->nullable();
            $table->string('dicatat_oleh')->nullable();
            // F-6 reversal
            $table->string('reverses')->nullable();          // id mutasi yang dibalik
            $table->string('reversed_by')->nullable();       // id entry pembalik
            $table->text('alasan_koreksi')->nullable();
            $table->timestamps();
            $table->foreign('gereja_id')->references('id')->on('gereja')->cascadeOnDelete();
            $table->foreign('pos_asal_id')->references('id')->on('pos_kas');
            $table->foreign('pos_tujuan_id')->references('id')->on('pos_kas');
            $table->index(['gereja_id', 'tanggal']);
        });

        // ── Pemasukan: cash basis (F-1) + pos (F-5) + reversal (F-6) ─────────
        Schema::table('pemasukan', function (Blueprint $table) {
            $table->enum('status_kas', ['sudah_diterima', 'belum_diterima'])
                  ->default('sudah_diterima')->after('status');
            $table->date('tanggal_diterima')->nullable()->after('status_kas');
            // Pos diisi saat KONFIRMASI diterima (KP-1) → nullable
            $table->string('pos_kas_id')->nullable()->after('gereja_id');
            // F-6
            $table->string('reverses')->nullable();
            $table->string('reversed_by')->nullable();
            $table->text('alasan_koreksi')->nullable();
            $table->foreign('pos_kas_id')->references('id')->on('pos_kas')->nullOnDelete();
        });

        // ── Pengeluaran: cash basis + pos (dipilih saat input) + reversal ───
        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->enum('status_kas', ['sudah_dikeluarkan', 'belum_dikeluarkan'])
                  ->default('sudah_dikeluarkan')->after('keterangan');
            $table->date('tanggal_dikeluarkan')->nullable()->after('status_kas');
            $table->string('pos_kas_id')->nullable()->after('gereja_id');
            $table->string('reverses')->nullable();
            $table->string('reversed_by')->nullable();
            $table->text('alasan_koreksi')->nullable();
            $table->foreign('pos_kas_id')->references('id')->on('pos_kas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pemasukan', function (Blueprint $table) {
            $table->dropForeign(['pos_kas_id']);
            $table->dropColumn(['status_kas', 'tanggal_diterima', 'pos_kas_id', 'reverses', 'reversed_by', 'alasan_koreksi']);
        });
        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->dropForeign(['pos_kas_id']);
            $table->dropColumn(['status_kas', 'tanggal_dikeluarkan', 'pos_kas_id', 'reverses', 'reversed_by', 'alasan_koreksi']);
        });
        Schema::dropIfExists('mutasi_kas');
        Schema::dropIfExists('pos_kas');
        Schema::table('gereja', function (Blueprint $table) {
            $table->dropColumn('multi_pos_kas');
        });
    }
};

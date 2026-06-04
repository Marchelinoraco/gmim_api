<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pemasukan', function (Blueprint $table) {
            // Sumber & status — default 'approved' agar data lama tetap terhitung
            $table->string('sumber')->default('manual')->after('gereja_id');    // manual | midtrans
            $table->string('status')->default('approved')->after('sumber');     // pending | approved | rejected

            // Audit trail
            $table->string('bukti_gambar')->nullable()->after('keterangan');    // path file bukti
            $table->string('input_by')->nullable()->after('bukti_gambar');      // church_users.id
            $table->string('approved_by')->nullable()->after('input_by');       // church_users.id
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejected_reason')->nullable()->after('approved_at');

            // Soft delete
            $table->softDeletes();

            // Index untuk filter status yang sering dipakai
            $table->index(['gereja_id', 'status']);

            $table->foreign('input_by')->references('id')->on('church_users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('church_users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pemasukan', function (Blueprint $table) {
            $table->dropForeign(['input_by']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['gereja_id', 'status']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'sumber', 'status', 'bukti_gambar',
                'input_by', 'approved_by', 'approved_at', 'rejected_reason',
            ]);
        });
    }
};

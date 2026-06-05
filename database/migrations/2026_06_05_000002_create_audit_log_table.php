<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('gereja_id')->nullable()->index();
            $table->string('actor_id')->nullable();   // church_user.id
            $table->string('actor_email')->nullable();
            $table->string('action');                 // login, logout, approve, reject, tutup_buku, dll
            $table->string('entity_type')->nullable();// pemasukan, pengeluaran, aset, ...
            $table->string('entity_id')->nullable();
            $table->json('meta')->nullable();         // data tambahan (perubahan, reason, dll)
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['gereja_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};

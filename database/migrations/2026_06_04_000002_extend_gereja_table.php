<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gereja', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('nama'); // path tenant /g/<slug>
            $table->string('subdomain')->nullable()->after('slug');     // disiapkan untuk masa depan
            $table->string('nama_pendeta')->nullable()->after('alamat');
            $table->string('telepon')->nullable()->after('nama_pendeta');
            $table->string('email')->nullable()->after('telepon');

            // Cache status langganan (sumber kebenaran = tabel langganan, Fase 4)
            $table->string('status_langganan')->default('trial')->after('email');
            $table->date('bergabung_pada')->nullable()->after('status_langganan');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('gereja', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'slug', 'subdomain', 'nama_pendeta', 'telepon',
                'email', 'status_langganan', 'bergabung_pada',
            ]);
        });
    }
};

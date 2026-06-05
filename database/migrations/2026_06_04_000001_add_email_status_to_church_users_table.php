<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_users', function (Blueprint $table) {
            // Email unik global = kunci login tanpa pilih gereja (KF-2)
            $table->string('email')->nullable()->after('nama');
            $table->string('status')->default('active')->after('password'); // active | disabled
            $table->timestamp('login_terakhir')->nullable()->after('status');

            // Disiapkan untuk upgrade ke SSO kelak (doc 07), kosong untuk sekarang
            $table->string('sso_user_id')->nullable()->after('login_terakhir');

            $table->softDeletes();
        });

        // Email unik global — diterapkan terpisah agar nullable dulu bisa diisi
        Schema::table('church_users', function (Blueprint $table) {
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('church_users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropSoftDeletes();
            $table->dropColumn(['email', 'status', 'login_terakhir', 'sso_user_id']);
        });
    }
};

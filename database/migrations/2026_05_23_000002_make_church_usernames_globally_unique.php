<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('church_users')->where('id', 'user-001')->update(['username' => 'bendahara-bethesda']);
        DB::table('church_users')->where('id', 'user-002')->update(['username' => 'bendahara-eben-haezer']);
        DB::table('church_users')->where('id', 'user-003')->update(['username' => 'pelayan-bethesda']);
        DB::table('church_users')->where('id', 'user-004')->update(['username' => 'pelayan-eben-haezer']);
        DB::table('church_users')->where('id', 'user-005')->update(['username' => 'bendahara-tumpaan-1']);

        Schema::table('church_users', function (Blueprint $table) {
            $table->index('gereja_id', 'church_users_gereja_id_index');
            $table->dropUnique('church_users_gereja_id_username_unique');
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('church_users', function (Blueprint $table) {
            $table->dropUnique('church_users_username_unique');
            $table->unique(['gereja_id', 'username']);
            $table->dropIndex('church_users_gereja_id_index');
        });
    }
};

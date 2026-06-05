<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('platform_admins')->upsert([
            [
                'id'         => 'admin-001',
                'nama'       => 'Super Admin GMIM',
                'email'      => 'admin@gmim.app',
                'password'   => Hash::make('admin123'),
                'role'       => 'super_admin',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['id'], ['nama', 'email', 'role', 'is_active', 'updated_at']);
    }
}

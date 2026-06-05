<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChurchUser;
use App\Models\Gereja;
use App\Models\Langganan;
use App\Models\PosKas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Login by email global — tanpa perlu memilih gereja terlebih dahulu (KF-2)
        // withoutGlobalScopes: login harus bypass Global Scope BelongsToGereja
        // karena scope belum aktif (belum ada currentGerejaId di container)
        $user = ChurchUser::withoutGlobalScopes()
            ->with('gereja')
            ->where('email', $validated['email'])
            ->where('status', 'active')
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'Email atau password salah.'], 401);
        }

        // Hapus token lama agar tidak menumpuk
        $user->tokens()->delete();

        $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

        // Catat waktu login terakhir
        $user->login_terakhir = now();
        $user->saveQuietly();

        AuditLog::record(
            action:      'login',
            gerejaId:    $user->gereja_id,
            actorId:     $user->id,
            actorEmail:  $user->email,
            ip:          $request->ip(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        $user        = $request->user()->load('gereja');
        $tokenName   = $user->currentAccessToken()->name ?? '';
        $impersonated = str_starts_with($tokenName, 'impersonate-by-');

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->formatUser($user), [
                'isImpersonated' => $impersonated,
            ]),
        ]);
    }

    public function register(Request $request)
    {
        $reserved = [
            'api', 'auth', 'login', 'logout', 'register', 'daftar', 'demo',
            'admin', 'superadmin', 'www', 'mail', 'ftp', 'app', 'docs',
            'g', 'gereja', 'dashboard', 'static', 'cdn', 'assets', 'support', 'billing',
        ];

        $validated = $request->validate([
            'namaGereja' => ['required', 'string', 'min:3', 'max:100'],
            'slug'       => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9-]+$/'],
            'nama'       => ['required', 'string', 'min:2', 'max:100'],
            'email'      => ['required', 'email', 'unique:church_users,email'],
            'password'   => ['required', 'string', 'min:8'],
        ]);

        $slug = $validated['slug'];

        if (in_array($slug, $reserved)) {
            return response()->json([
                'success' => false,
                'errors'  => ['slug' => ['Slug ini tidak dapat digunakan.']],
            ], 422);
        }

        if (Gereja::where('slug', $slug)->exists()) {
            return response()->json([
                'success' => false,
                'errors'  => ['slug' => ['Slug sudah dipakai gereja lain.']],
            ], 422);
        }

        try {
            $result = DB::transaction(function () use ($validated, $slug) {
                $gerejaId = 'gmim-' . Str::ulid();
                $now      = now();

                Gereja::create([
                    'id'               => $gerejaId,
                    'nama'             => $validated['namaGereja'],
                    'slug'             => $slug,
                    'status_langganan' => 'trial',
                    'bergabung_pada'   => $now->toDateString(),
                ]);

                $user = ChurchUser::create([
                    'id'        => 'user-' . Str::ulid(),
                    'gereja_id' => $gerejaId,
                    'nama'      => $validated['nama'],
                    'email'     => $validated['email'],
                    'password'  => Hash::make($validated['password']),
                    'role'      => 'Bendahara',
                    'username'  => $slug . '-admin',
                    'status'    => 'active',
                ]);

                // Langganan trial 14 hari (KF-3)
                Langganan::create([
                    'id'             => 'sub-' . Str::ulid(),
                    'gereja_id'      => $gerejaId,
                    'paket_id'       => 'paket-basic',
                    'status'         => 'trial',
                    'siklus'         => 'bulanan',
                    'trial_berakhir' => $now->copy()->addDays(14)->toDateString(),
                ]);

                // Seed 3 pos kas default (F-5) — saldo awal 0, diisi dari pengaturan
                PosKas::create(['id' => 'pos-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Tunai',        'tipe' => 'tunai',    'saldo_awal' => 0, 'is_aktif' => true, 'urutan' => 1]);
                PosKas::create(['id' => 'pos-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Rekening BSG', 'tipe' => 'bank', 'nama_bank' => 'Bank Sulut Go', 'saldo_awal' => 0, 'is_aktif' => true, 'urutan' => 2]);
                PosKas::create(['id' => 'pos-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Midtrans',     'tipe' => 'midtrans', 'saldo_awal' => 0, 'is_aktif' => true, 'urutan' => 3]);

                // Seed kategori default
                DB::table('kategori_persembahan')->insert([
                    ['id' => 'kp-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Persembahan Minggu',   'created_at' => $now, 'updated_at' => $now],
                    ['id' => 'kp-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Persembahan Syukur',   'created_at' => $now, 'updated_at' => $now],
                    ['id' => 'kp-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Persembahan Diakonia', 'created_at' => $now, 'updated_at' => $now],
                ]);
                DB::table('kategori_pengeluaran')->insert([
                    ['id' => 'ke-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Operasional',  'created_at' => $now, 'updated_at' => $now],
                    ['id' => 'ke-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Pembangunan',  'created_at' => $now, 'updated_at' => $now],
                    ['id' => 'ke-' . Str::ulid(), 'gereja_id' => $gerejaId, 'nama' => 'Gaji & Honor', 'created_at' => $now, 'updated_at' => $now],
                ]);

                $user->load('gereja');
                $token = $user->createToken('auth-token', ['*'], now()->addDays(30))->plainTextToken;

                return compact('user', 'token');
            });

            return response()->json([
                'success' => true,
                'message' => 'Gereja berhasil didaftarkan.',
                'token'   => $result['token'],
                'user'    => $this->formatUser($result['user']),
            ], 201);

        } catch (\Exception) {
            return response()->json(['success' => false, 'message' => 'Pendaftaran gagal. Coba lagi.'], 500);
        }
    }

    public function checkSlug(Request $request)
    {
        $reserved = [
            'api', 'auth', 'login', 'logout', 'register', 'daftar', 'demo',
            'admin', 'superadmin', 'www', 'mail', 'ftp', 'app', 'docs',
            'g', 'gereja', 'dashboard', 'static', 'cdn', 'assets', 'support', 'billing',
        ];

        $slug = strtolower(trim($request->query('slug', '')));

        if (empty($slug) || ! preg_match('/^[a-z0-9-]{3,50}$/', $slug)) {
            return response()->json(['available' => false, 'slug' => $slug]);
        }

        if (in_array($slug, $reserved)) {
            return response()->json(['available' => false, 'slug' => $slug, 'reason' => 'reserved']);
        }

        $exists = Gereja::where('slug', $slug)->exists();
        return response()->json(['available' => ! $exists, 'slug' => $slug]);
    }

    public function demoLogin()
    {
        $demo = ChurchUser::withoutGlobalScopes()
            ->with('gereja')
            ->where('email', 'demo@gmim.app')
            ->where('status', 'active')
            ->first();

        if (! $demo) {
            return response()->json(['success' => false, 'message' => 'Demo tidak tersedia.'], 404);
        }

        $demo->tokens()->delete();
        $token = $demo->createToken('demo-token', ['*'], now()->addHours(4))->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Demo berhasil dimulai.',
            'token'   => $token,
            'user'    => $this->formatUser($demo),
        ]);
    }

    private function formatUser(ChurchUser $user): array
    {
        return [
            'id'         => $user->id,
            'nama'       => $user->nama,
            'email'      => $user->email,
            'role'       => $user->role,
            'gerejaId'   => $user->gereja_id,
            'gerejaNama' => $user->gereja?->nama,
            'slug'       => $user->gereja?->slug,
            'username'   => $user->username,
            'isDemo'     => $user->email === 'demo@gmim.app',
        ];
    }
}

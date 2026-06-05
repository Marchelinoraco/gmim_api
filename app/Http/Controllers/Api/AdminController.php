<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChurchUser;
use App\Models\Gereja;
use App\Models\Langganan;
use App\Models\PlatformAdmin;
use App\Models\Tagihan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // -----------------------------------------------------------------------
    // Auth
    // -----------------------------------------------------------------------

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $admin = PlatformAdmin::where('email', $validated['email'])
            ->where('is_active', true)
            ->first();

        if (! $admin || ! Hash::check($validated['password'], $admin->password)) {
            return response()->json(['success' => false, 'message' => 'Email atau password salah.'], 401);
        }

        $admin->tokens()->delete();
        $token = $admin->createToken('admin-token', ['*'], now()->addHours(8))->plainTextToken;
        $admin->update(['login_terakhir' => now()]);

        AuditLog::record('admin_login', null, null, null, [], $admin->id, $admin->email, $request->ip());

        return response()->json([
            'success' => true,
            'token'   => $token,
            'admin'   => $this->formatAdmin($admin),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logout berhasil.']);
    }

    public function me(Request $request)
    {
        return response()->json(['success' => true, 'admin' => $this->formatAdmin($request->user())]);
    }

    // -----------------------------------------------------------------------
    // Stats
    // -----------------------------------------------------------------------

    public function stats()
    {
        $totalGereja    = Gereja::withoutGlobalScopes()->count();
        $totalPengguna  = ChurchUser::withoutGlobalScopes()->where('email', '!=', 'demo@gmim.app')->count();
        $trial          = Langganan::where('status', 'trial')->count();
        $active         = Langganan::where('status', 'active')->count();
        $expired        = Langganan::whereIn('status', ['expired', 'past_due'])->count();
        $pendapatanBulan= Tagihan::where('status', 'paid')
            ->whereMonth('dibayar_pada', now()->month)
            ->sum('jumlah');

        return response()->json([
            'success' => true,
            'data'    => compact('totalGereja', 'totalPengguna', 'trial', 'active', 'expired', 'pendapatanBulan'),
        ]);
    }

    // -----------------------------------------------------------------------
    // Gereja Management
    // -----------------------------------------------------------------------

    public function gerejaIndex()
    {
        $gereja = Gereja::withoutGlobalScopes()
            ->with('langganan.paket')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($g) => $this->formatGereja($g));

        return response()->json(['success' => true, 'data' => $gereja]);
    }

    public function gerejaShow(string $id)
    {
        $gereja = Gereja::withoutGlobalScopes()->with('langganan.paket')->findOrFail($id);

        $pengguna = ChurchUser::withoutGlobalScopes()
            ->where('gereja_id', $id)
            ->select('id', 'nama', 'email', 'role', 'status', 'login_terakhir')
            ->get();

        $midtrans = DB::table('gereja_midtrans')->where('gereja_id', $id)->first();

        return response()->json([
            'success' => true,
            'data'    => array_merge($this->formatGereja($gereja), [
                'pengguna'        => $pengguna,
                'midtransAktif'   => $midtrans?->is_active ?? false,
                'midtransProduksi'=> $midtrans?->is_production ?? false,
            ]),
        ]);
    }

    public function gerejaUpdate(Request $request, string $id)
    {
        $gereja = Gereja::withoutGlobalScopes()->findOrFail($id);

        $validated = $request->validate([
            'nama'         => ['sometimes', 'string', 'min:3', 'max:100'],
            'alamat'       => ['sometimes', 'nullable', 'string'],
            'nama_pendeta' => ['sometimes', 'nullable', 'string'],
            'telepon'      => ['sometimes', 'nullable', 'string'],
            'email'        => ['sometimes', 'nullable', 'email'],
        ]);

        $gereja->update($validated);

        AuditLog::record('admin_update_gereja', $gereja->id, 'gereja', $gereja->id,
            $validated, $request->user()->id, $request->user()->email, $request->ip());

        return response()->json(['success' => true, 'data' => $this->formatGereja($gereja->fresh())]);
    }

    // -----------------------------------------------------------------------
    // Langganan Override
    // -----------------------------------------------------------------------

    public function langgananIndex()
    {
        $list = Langganan::with(['gereja', 'paket'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($l) => $this->formatLangganan($l));

        return response()->json(['success' => true, 'data' => $list]);
    }

    public function langgananOverride(Request $request, string $id)
    {
        $validated = $request->validate([
            'status'         => ['sometimes', 'in:trial,active,past_due,expired,canceled'],
            'berakhir'       => ['sometimes', 'nullable', 'date'],
            'trial_berakhir' => ['sometimes', 'nullable', 'date'],
            'paket_id'       => ['sometimes', 'string', 'exists:paket,id'],
            'catatan'        => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $langganan = Langganan::with('gereja')->findOrFail($id);
        $sebelum   = $langganan->only(['status', 'berakhir', 'trial_berakhir', 'paket_id']);

        $langganan->update(array_filter($validated, fn ($_, $k) => $k !== 'catatan', ARRAY_FILTER_USE_BOTH));

        // Sinkronkan cache gereja
        if (isset($validated['status'])) {
            Gereja::withoutGlobalScopes()
                ->where('id', $langganan->gereja_id)
                ->update(['status_langganan' => $validated['status']]);
        }

        AuditLog::record(
            'admin_override_langganan', $langganan->gereja_id, 'langganan', $langganan->id,
            ['sebelum' => $sebelum, 'sesudah' => $validated, 'catatan' => $validated['catatan'] ?? null],
            $request->user()->id, $request->user()->email, $request->ip(),
        );

        return response()->json(['success' => true, 'data' => $this->formatLangganan($langganan->fresh(['gereja', 'paket']))]);
    }

    // -----------------------------------------------------------------------
    // Pengguna (lintas gereja, read-only + admin aksi)
    // -----------------------------------------------------------------------

    public function penggunaIndex(Request $request)
    {
        $gerejaId = $request->query('gereja_id');

        $query = ChurchUser::withoutGlobalScopes()
            ->with('gereja')
            ->where('email', '!=', 'demo@gmim.app');

        if ($gerejaId) {
            $query->where('gereja_id', $gerejaId);
        }

        $users = $query->orderByDesc('created_at')->get()
            ->map(fn ($u) => [
                'id'          => $u->id,
                'nama'        => $u->nama,
                'email'       => $u->email,
                'role'        => $u->role,
                'status'      => $u->status,
                'gerejaId'    => $u->gereja_id,
                'gerejaNama'  => $u->gereja?->nama,
                'loginTerakhir' => $u->login_terakhir?->toDateTimeString(),
            ]);

        return response()->json(['success' => true, 'data' => $users]);
    }

    // -----------------------------------------------------------------------
    // Impersonasi — "Masuk sebagai" gereja user (ber-audit)
    // -----------------------------------------------------------------------

    public function impersonate(Request $request, string $userId)
    {
        $target = ChurchUser::withoutGlobalScopes()->with('gereja')->findOrFail($userId);
        $admin  = $request->user();

        // Hapus token impersonasi lama dari admin ini untuk user ini
        $target->tokens()->where('name', 'like', 'impersonate-%')->delete();

        $token = $target->createToken(
            'impersonate-by-' . $admin->id,
            ['*'],
            now()->addHours(2)
        )->plainTextToken;

        AuditLog::record(
            'admin_impersonate', $target->gereja_id, 'church_user', $userId,
            ['target_email' => $target->email, 'target_gereja' => $target->gereja?->nama],
            $admin->id, $admin->email, $request->ip(),
        );

        return response()->json([
            'success'  => true,
            'message'  => 'Token impersonasi dibuat. Berlaku 2 jam.',
            'token'    => $token,
            'user'     => [
                'id'           => $target->id,
                'nama'         => $target->nama,
                'email'        => $target->email,
                'role'         => $target->role,
                'gerejaId'     => $target->gereja_id,
                'gerejaNama'   => $target->gereja?->nama,
                'slug'         => $target->gereja?->slug,
                'isImpersonated'   => true,
                'impersonatedBy'   => $admin->nama,
            ],
            'manageUrl' => rtrim(config('app.frontend_url', 'http://localhost:5173'), '/')
                           . '/g/' . $target->gereja?->slug . '/dashboard',
        ]);
    }

    public function stopImpersonate(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Sesi impersonasi diakhiri.']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function formatAdmin(PlatformAdmin $admin): array
    {
        return [
            'id'           => $admin->id,
            'nama'         => $admin->nama,
            'email'        => $admin->email,
            'role'         => $admin->role,
            'loginTerakhir'=> $admin->login_terakhir?->toDateTimeString(),
        ];
    }

    private function formatGereja(Gereja $g): array
    {
        $l = $g->relationLoaded('langganan') ? $g->langganan : null;
        return [
            'id'               => $g->id,
            'nama'             => $g->nama,
            'slug'             => $g->slug,
            'alamat'           => $g->alamat,
            'namaPendeta'      => $g->nama_pendeta,
            'telepon'          => $g->telepon,
            'email'            => $g->email,
            'statusLangganan'  => $g->status_langganan,
            'bergabungPada'    => $g->bergabung_pada,
            'langganan'        => $l ? $this->formatLangganan($l) : null,
        ];
    }

    private function formatLangganan(Langganan $l): array
    {
        return [
            'id'            => $l->id,
            'gerejaId'      => $l->gereja_id,
            'gerejaNama'    => $l->relationLoaded('gereja') ? $l->gereja?->nama : null,
            'paketId'       => $l->paket_id,
            'paketNama'     => $l->relationLoaded('paket') ? $l->paket?->nama : null,
            'status'        => $l->statusEfektif(),
            'siklus'        => $l->siklus,
            'trialBerakhir' => $l->trial_berakhir?->toDateString(),
            'mulai'         => $l->mulai?->toDateString(),
            'berakhir'      => $l->berakhir?->toDateString(),
            'trialSisaHari' => $l->trialSisaHari(),
        ];
    }
}

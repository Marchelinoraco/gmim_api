<?php

use App\Http\Controllers\Api\ArusKasController;
use App\Http\Controllers\Api\AsetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChurchUserController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GajiController;
use App\Http\Controllers\Api\GerejaController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\KasController;
use App\Http\Controllers\Api\LanggananController;
use App\Http\Controllers\Api\MidtransConfigController;
use App\Http\Controllers\Api\MidtransController;
use App\Http\Controllers\Api\MutasiKasController;
use App\Http\Controllers\Api\PosKasController;
use App\Http\Middleware\EnsureBendahara;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureUserBelongsToGereja;
use App\Http\Middleware\SetCurrentGereja;
use Illuminate\Support\Facades\Route;

// Publik
Route::get('/health', function () {
    $checks = [];

    // DB check
    try {
        \Illuminate\Support\Facades\DB::select('SELECT 1');
        $checks['db'] = 'ok';
    } catch (\Throwable) {
        $checks['db'] = 'error';
    }

    // Queue check (pastikan tabel jobs ada)
    try {
        \Illuminate\Support\Facades\DB::table('jobs')->count();
        $checks['queue'] = 'ok';
    } catch (\Throwable) {
        $checks['queue'] = 'unavailable';
    }

    $allOk = ! in_array('error', $checks);

    return response()->json([
        'status'   => $allOk ? 'ok' : 'degraded',
        'checks'   => $checks,
        'time'     => now()->toISOString(),
        'version'  => config('app.version', '1.0.0'),
    ], $allOk ? 200 : 503);
});
Route::post('/login',      [AuthController::class, 'login'])->middleware('throttle:auth');
Route::post('/register',   [AuthController::class, 'register'])->middleware('throttle:register');
Route::get('/check-slug',  [AuthController::class, 'checkSlug']);
Route::post('/demo-login', [AuthController::class, 'demoLogin'])->middleware('throttle:auth');
Route::get('/gereja', [GerejaController::class, 'index']);
Route::get('/gereja/{gereja}', [GerejaController::class, 'show']);

// Webhook Midtrans — publik (dipanggil server Midtrans, bukan user)
Route::post('/midtrans/notification', [MidtransController::class, 'handleNotification']);

// Webhook billing langganan (akun PLATFORM — terpisah dari webhook gereja)
Route::post('/billing/midtrans/notification', [LanggananController::class, 'handleBillingWebhook']);

// Katalog paket — publik (untuk landing page / halaman daftar)
Route::get('/paket', [LanggananController::class, 'daftarPaket']);

// Butuh autentikasi Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Butuh autentikasi + user harus milik gereja yang diakses
    Route::prefix('gereja/{gereja}')
        ->middleware([EnsureUserBelongsToGereja::class, SetCurrentGereja::class, EnsureSubscriptionActive::class])
        ->group(function () {
            // Billing / Langganan
            Route::get('/langganan', [LanggananController::class, 'show']);
            Route::get('/tagihan', [LanggananController::class, 'tagihanIndex']);
            Route::post('/langganan/checkout', [LanggananController::class, 'checkout'])->middleware('throttle:checkout');
            Route::get('/pengguna', [ChurchUserController::class, 'index']);
            Route::post('/pengguna', [ChurchUserController::class, 'store']);
            Route::put('/pengguna/{id}', [ChurchUserController::class, 'update']);
            Route::delete('/pengguna/{id}', [ChurchUserController::class, 'destroy']);

            Route::get('/dashboard', [FinanceController::class, 'dashboard']);
            Route::get('/dashboard/grafik', [FinanceController::class, 'dashboardGrafik']);

            // Arus Kas + Tutup Buku
            Route::get('/arus-kas', [ArusKasController::class, 'index']);
            Route::middleware(EnsureBendahara::class)->group(function () {
                Route::post('/tutup-buku', [ArusKasController::class, 'tutupBuku']);
                Route::post('/buka-buku',  [ArusKasController::class, 'bukaBuku']);
            });

            Route::get('/kategori-persembahan', [FinanceController::class, 'kategoriPersembahanIndex']);
            Route::post('/kategori-persembahan', [FinanceController::class, 'kategoriPersembahanStore']);
            Route::put('/kategori-persembahan/{id}', [FinanceController::class, 'kategoriPersembahanUpdate']);
            Route::delete('/kategori-persembahan/{id}', [FinanceController::class, 'kategoriPersembahanDestroy']);

            Route::get('/nama-persembahan', [FinanceController::class, 'namaPersembahanIndex']);
            Route::post('/nama-persembahan', [FinanceController::class, 'namaPersembahanStore']);
            Route::get('/kategori-persembahan/{id}/nama-persembahan', [FinanceController::class, 'namaPersembahanByKategori']);
            Route::put('/nama-persembahan/{id}', [FinanceController::class, 'namaPersembahanUpdate']);
            Route::delete('/nama-persembahan/{id}', [FinanceController::class, 'namaPersembahanDestroy']);

            Route::get('/pemasukan', [FinanceController::class, 'pemasukanIndex']);
            Route::post('/pemasukan', [FinanceController::class, 'pemasukanStore']);
            Route::post('/pemasukan/manual', [FinanceController::class, 'pemasukanStoreManual']);
            // Midtrans payment — buat order dan dapat Snap token
            Route::post('/pemasukan/midtrans', [MidtransController::class, 'createPayment']);
            // Batalkan transaksi pending_payment (popup ditutup tanpa bayar)
            Route::delete('/pemasukan/{id}/cancel-payment', [MidtransController::class, 'cancelPayment']);
            Route::get('/pemasukan/{id}', [FinanceController::class, 'pemasukanShow']);
            Route::put('/pemasukan/{id}', [FinanceController::class, 'pemasukanUpdate']);
            Route::delete('/pemasukan/{id}', [FinanceController::class, 'pemasukanDestroy']);

            // Approval — hanya Bendahara
            Route::middleware(EnsureBendahara::class)->group(function () {
                Route::put('/pemasukan/{id}/approve', [FinanceController::class, 'pemasukanApprove']);
                Route::put('/pemasukan/{id}/reject', [FinanceController::class, 'pemasukanReject']);
            });

            Route::get('/kategori-pengeluaran', [FinanceController::class, 'kategoriPengeluaranIndex']);
            Route::post('/kategori-pengeluaran', [FinanceController::class, 'kategoriPengeluaranStore']);
            Route::put('/kategori-pengeluaran/{id}', [FinanceController::class, 'kategoriPengeluaranUpdate']);
            Route::delete('/kategori-pengeluaran/{id}', [FinanceController::class, 'kategoriPengeluaranDestroy']);

            Route::get('/pengeluaran', [FinanceController::class, 'pengeluaranIndex']);
            Route::post('/pengeluaran', [FinanceController::class, 'pengeluaranStore']);
            Route::get('/pengeluaran/{id}', [FinanceController::class, 'pengeluaranShow']);
            Route::put('/pengeluaran/{id}', [FinanceController::class, 'pengeluaranUpdate']);
            Route::delete('/pengeluaran/{id}', [FinanceController::class, 'pengeluaranDestroy']);

            Route::get('/laporan/mingguan', [FinanceController::class, 'laporanMingguan']);
            Route::get('/laporan/bulanan', [FinanceController::class, 'laporanBulanan']);

            // Export data tenant (CSV)
            Route::get('/export', [ExportController::class, 'export']);

            // ── Multi-Pos Kas (F-5) + Cash Basis (F-1) + Reversal (F-6) ──────
            Route::get('/pos-kas',  [PosKasController::class, 'index']);
            Route::get('/arus-kas-pos', [KasController::class, 'arusKasPos']);
            Route::get('/transaksi-pending-kas', [KasController::class, 'pending']);

            Route::middleware(EnsureBendahara::class)->group(function () {
                Route::post('/pos-kas',      [PosKasController::class, 'store']);
                Route::put('/pos-kas/{id}',  [PosKasController::class, 'update']);

                Route::get('/mutasi-kas',    [MutasiKasController::class, 'index']);
                Route::post('/mutasi-kas',   [MutasiKasController::class, 'store']);
                Route::post('/mutasi-kas/{id}/koreksi', [MutasiKasController::class, 'koreksi']);

                Route::patch('/pemasukan/{id}/konfirmasi-kas',  [KasController::class, 'konfirmasiPemasukan']);
                Route::patch('/pengeluaran/{id}/konfirmasi-kas',[KasController::class, 'konfirmasiPengeluaran']);
                Route::post('/pemasukan/{id}/koreksi',   [KasController::class, 'koreksiPemasukan']);
                Route::post('/pengeluaran/{id}/koreksi', [KasController::class, 'koreksiPengeluaran']);
            });

            // Aset
            Route::get('/aset', [AsetController::class, 'index']);
            Route::get('/aset/{id}', [AsetController::class, 'show']);
            Route::middleware(EnsureBendahara::class)->group(function () {
                Route::post('/aset', [AsetController::class, 'store']);
                Route::put('/aset/{id}', [AsetController::class, 'update']);
                Route::delete('/aset/{id}', [AsetController::class, 'destroy']);
            });

            // Gaji & Honor
            Route::get('/pegawai', [GajiController::class, 'pegawaiIndex']);
            Route::get('/gaji', [GajiController::class, 'pembayaranIndex']);
            Route::middleware(EnsureBendahara::class)->group(function () {
                Route::post('/pegawai', [GajiController::class, 'pegawaiStore']);
                Route::put('/pegawai/{id}', [GajiController::class, 'pegawaiUpdate']);
                Route::delete('/pegawai/{id}', [GajiController::class, 'pegawaiDestroy']);
                Route::post('/gaji', [GajiController::class, 'pembayaranStore']);
                Route::delete('/gaji/{id}', [GajiController::class, 'pembayaranDestroy']);
                Route::put('/gaji/{id}/bayar', [GajiController::class, 'tandaiBayar']);
            });

            // Midtrans: ambil client_key untuk load Snap.js di frontend
            Route::get('/midtrans/client-key', [MidtransController::class, 'clientKey']);

            // Midtrans config — Bendahara kelola key sendiri
            Route::middleware(EnsureBendahara::class)->group(function () {
                Route::get('/midtrans/config',  [MidtransConfigController::class, 'show']);
                Route::put('/midtrans/config',  [MidtransConfigController::class, 'update']);
                Route::post('/midtrans/test',   [MidtransConfigController::class, 'test']);
            });
        });
});

// -----------------------------------------------------------------------
// Super Admin platform — guard terpisah (PlatformAdmin)
// -----------------------------------------------------------------------
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminController::class, 'login'])->middleware('throttle:auth');

    Route::middleware(['auth:sanctum', EnsurePlatformAdmin::class])->group(function () {
        Route::post('/logout', [AdminController::class, 'logout']);
        Route::get('/me',      [AdminController::class, 'me']);
        Route::get('/stats',   [AdminController::class, 'stats']);

        // Gereja
        Route::get('/gereja',           [AdminController::class, 'gerejaIndex']);
        Route::get('/gereja/{id}',      [AdminController::class, 'gerejaShow']);
        Route::put('/gereja/{id}',      [AdminController::class, 'gerejaUpdate']);

        // Langganan override
        Route::get('/langganan',        [AdminController::class, 'langgananIndex']);
        Route::put('/langganan/{id}',   [AdminController::class, 'langgananOverride']);

        // Pengguna lintas gereja
        Route::get('/pengguna',         [AdminController::class, 'penggunaIndex']);

        // Impersonasi (masuk sebagai)
        Route::post('/impersonate/{userId}', [AdminController::class, 'impersonate']);
        Route::delete('/impersonate',        [AdminController::class, 'stopImpersonate']);
    });
});

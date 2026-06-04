<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChurchUserController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GerejaController;
use App\Http\Controllers\Api\MidtransController;
use App\Http\Middleware\EnsureBendahara;
use App\Http\Middleware\EnsureUserBelongsToGereja;
use Illuminate\Support\Facades\Route;

// Publik
Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/login', [AuthController::class, 'login']);
Route::get('/gereja', [GerejaController::class, 'index']);
Route::get('/gereja/{gereja}', [GerejaController::class, 'show']);

// Webhook Midtrans — publik (dipanggil server Midtrans, bukan user)
// Harus di luar middleware auth agar Midtrans bisa POST tanpa token
Route::post('/midtrans/notification', [MidtransController::class, 'handleNotification']);

// Butuh autentikasi Sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Butuh autentikasi + user harus milik gereja yang diakses
    Route::prefix('gereja/{gereja}')
        ->middleware(EnsureUserBelongsToGereja::class)
        ->group(function () {
            Route::get('/pengguna', [ChurchUserController::class, 'index']);
            Route::post('/pengguna', [ChurchUserController::class, 'store']);
            Route::put('/pengguna/{id}', [ChurchUserController::class, 'update']);
            Route::delete('/pengguna/{id}', [ChurchUserController::class, 'destroy']);

            Route::get('/dashboard', [FinanceController::class, 'dashboard']);

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

            // Midtrans: ambil client_key untuk load Snap.js di frontend
            Route::get('/midtrans/client-key', [MidtransController::class, 'clientKey']);
        });
});

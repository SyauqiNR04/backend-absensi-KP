<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Import Controller yang dibutuhkan
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SettingController;

// ==========================================
// AREA PUBLIK (Tidak butuh Token / Login)
// ==========================================

// 1. Karyawan melakukan Login dari HP
Route::post('/login', [AuthController::class, 'login']);

// 2. Aplikasi mengambil koordinat & radius kantor (Bisa diletakkan di luar agar HP bisa mengecek lokasi sebelum absen)
Route::get('/settings', [SettingController::class, 'index']);

// 3. Mendaftarkan karyawan via API (Opsional, karena sekarang Anda sudah punya form Tambah Karyawan di Web)
Route::post('/employees', [EmployeeController::class, 'store']);


// ==========================================
// AREA TERTUTUP (Wajib bawa Token dari Login)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    
    // 1. Cek profil karyawan yang sedang login
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    });

    // 2. Karyawan Logout dari HP
    Route::post('/logout', [AuthController::class, 'logout']);

    // 3. Karyawan mengirim data absensi masuk/pulang
    Route::post('/attendances', [AttendanceController::class, 'store']);

    // 4. Aplikasi mengambil riwayat absensi karyawan tersebut
    Route::get('/attendances/{nip}', [AttendanceController::class, 'history']);
    Route::get('/history/{nip}', [AttendanceController::class, 'history']); // Rute alternatif
});
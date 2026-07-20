<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminEmployeeController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminGeoFenceController;
use App\Http\Controllers\AdminVerificationController;

// Halaman utama default diarahkan ke login
Route::get('/', function () {
    return redirect('/admin/login');
});

// ==========================================
// RUTE AUTENTIKASI (LOGIN & LOGOUT)
// ==========================================
Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
Route::post('/admin/login', [AdminAuthController::class, 'login']);
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('logout');

// ==========================================
// RUTE PANEL ADMIN (DILINDUNGI PASSWORD)
// ==========================================
Route::middleware('auth')->group(function () {
    
    // 1. Dashboard Overview (Grafik) -> Arahkan ke fungsi 'summary'
    Route::get('/admin/dashboard', [DashboardController::class, 'summary'])->name('admin.dashboard');
    
    // 2. Riwayat Absensi (Tabel) -> Arahkan ke fungsi 'riwayat'
    Route::get('/admin/riwayat', [DashboardController::class, 'riwayat'])->name('admin.riwayat');
    
    // 3. Export Data Excel/PDF
    Route::get('/admin/export/{type}', [DashboardController::class, 'export'])->name('admin.export');
    
    // 4. Manajemen Karyawan (CRUD)
    // DELETE di sini menonaktifkan, bukan menghapus (riwayat absensi dijaga).
    Route::resource('/admin/employees', AdminEmployeeController::class);
    Route::post('/admin/employees/{id}/activate', [AdminEmployeeController::class, 'activate'])
        ->name('admin.employees.activate');

    // Foto referensi wajah tersimpan di disk privat, jadi tidak bisa ditautkan
    // langsung dari <img src> -- rute ini menyajikannya khusus admin.
    Route::get('/admin/employees/{id}/photo', [AdminEmployeeController::class, 'photo'])
        ->name('admin.employees.photo');
    Route::delete('/admin/employees/{id}/photo', [AdminEmployeeController::class, 'deletePhoto'])
        ->name('admin.employees.photo.destroy');
    
    // 5. Tinjauan bukti verifikasi wajah (absensi yang ditandai janggal)
    Route::get('/admin/verifikasi', [AdminVerificationController::class, 'index'])
        ->name('admin.verifikasi');
    Route::get('/admin/verifikasi/{evidence}/photo', [AdminVerificationController::class, 'photo'])
        ->name('admin.verifikasi.photo');

    // Foto absensi untuk halaman Riwayat. Berkasnya di disk privat sehingga
    // tidak bisa ditautkan lewat asset('storage/...').
    Route::get('/admin/attendances/{id}/photo/{type}', [AdminVerificationController::class, 'attendancePhoto'])
        ->whereNumber('id')
        ->name('admin.attendances.photo');

    // 6. Konfigurasi Geo-Fence & Waktu
    Route::get('/admin/geofence', [AdminGeoFenceController::class, 'index']);
    Route::post('/admin/geofence', [AdminGeoFenceController::class, 'update']);
}); 
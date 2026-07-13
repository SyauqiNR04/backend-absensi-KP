<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdminEmployeeController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminGeoFenceController;

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
    Route::resource('/admin/employees', AdminEmployeeController::class);
    
    // 5. Konfigurasi Geo-Fence & Waktu
    Route::get('/admin/geofence', [AdminGeoFenceController::class, 'index']);
    Route::post('/admin/geofence', [AdminGeoFenceController::class, 'update']);
}); 
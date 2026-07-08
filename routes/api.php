<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SettingController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Jalur API untuk mendaftarkan karyawan
Route::post('/employees', [EmployeeController::class, 'store']);

// Jalur API untuk memproses data absensi
Route::post('/attendances', [AttendanceController::class, 'store']);

// Jalur API untuk melihat riwayat absen berdasarkan NIP
Route::get('/attendances/{nip}', [AttendanceController::class, 'history']);

// Jalur API untuk mengambil titik lokasi dan radius kantor
Route::get('/settings', [SettingController::class, 'index']);
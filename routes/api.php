<?php
/*
|==================================================================
| FITUR: Rute API
| Memetakan endpoint publik/tertutup beserta middleware keamanan (throttle, ability, device.integrity, attestation).
|==================================================================
*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SettingController;

/*
|--------------------------------------------------------------------------
| API Routes (HARDENED)
|--------------------------------------------------------------------------
|  - /login dibungkus limiter 'login' (anti brute force, per NIP+IP).
|  - POST /employees (registrasi publik) DIHAPUS.
|  - /refresh merotasi token sebelum kedaluwarsa (Phase 2).
|  - POST /attendances: ability token + device integrity.
*/

// ======================= AREA PUBLIK =======================
Route::middleware('throttle:api')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    Route::get('/settings', [SettingController::class, 'index']);
});

// ======================= AREA TERTUTUP =======================
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', fn (Request $request) => response()->json([
        'success' => true,
        'data'    => $request->user(),
    ]));

    // Rotasi token (harus dipanggil selagi token masih valid).
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::post('/logout', [AuthController::class, 'logout']);

    // Ganti password (butuh password lama + kebijakan kuat).
    Route::post('/password', [PasswordController::class, 'change']);

    Route::post('/attendances', [AttendanceController::class, 'store'])
        ->middleware(['ability:attendance:submit', 'device.integrity', 'attestation']);

    // Harus didaftarkan SEBELUM /attendances/{nip} agar "today" tidak
    // tertangkap sebagai wildcard {nip}.
    Route::get('/attendances/today', [AttendanceController::class, 'today'])
        ->middleware('ability:attendance:read');

    Route::get('/attendances/{nip}', [AttendanceController::class, 'history'])
        ->middleware('ability:attendance:read');
    Route::get('/history/{nip}', [AttendanceController::class, 'history'])
        ->middleware('ability:attendance:read');

    // Foto referensi wajah (untuk face matching di klien). Identitas selalu
    // dari token, bukan parameter URL -> tidak butuh {nip}.
    Route::get('/reference-photo', [AttendanceController::class, 'referencePhoto'])
        ->middleware('ability:attendance:submit');
});

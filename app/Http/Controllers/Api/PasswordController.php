<?php
/*
|==================================================================
| FITUR: Ganti/Reset Password
| Endpoint terautentikasi untuk mengganti password: verifikasi password
| lama, simpan hash Argon2id, cabut sesi lain, dan catat audit.
|==================================================================
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class PasswordController extends Controller
{
    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $employee = $request->user();

        // Verifikasi password lama sebelum mengizinkan perubahan.
        if (! Hash::check($request->input('current_password'), $employee->password)) {
            AuditLog::record('password.change_failed', [
                'employee_id' => $employee->id,
                'severity'    => 'warning',
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai.',
            ], 422);
        }

        // Simpan hash baru (driver argon2id dari config/hashing.php).
        $employee->password = Hash::make($request->input('password'));
        $employee->save();

        // Praktik keamanan: cabut SEMUA sesi lain, sisakan token saat ini.
        $currentId = $request->user()->currentAccessToken()->id;
        $employee->tokens()->where('id', '!=', $currentId)->delete();

        AuditLog::record('password.changed', [
            'employee_id' => $employee->id,
            'severity'    => 'info',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui. Sesi lain telah dikeluarkan.',
        ], 200);
    }
}

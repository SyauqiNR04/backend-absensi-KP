<?php
/*
|==================================================================
| FITUR: Autentikasi
| Login berpassword dengan respons generik anti-enumeration, rotasi token (/refresh), logout, dan audit login gagal.
|==================================================================
*/

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * AuthController (HARDENED + Phase 2: token rotation)
 * -------------------------------------------------------------------------
 * - login()   : wajib password, respons generik (anti user-enumeration),
 *               token ber-ability terbatas & berumur pendek (config sanctum).
 * - refresh() : merotasi token — token lama DIHAPUS, token baru diterbitkan.
 *               Rotasi mempersempit jendela pemakaian token curian dan
 *               memberi akses baru tanpa memaksa user login ulang.
 * - logout()  : mencabut token aktif.
 */
class AuthController extends Controller
{
    /** Ability default untuk klien absensi mobile. */
    private const MOBILE_ABILITIES = ['attendance:submit', 'attendance:read'];

    /** Hash umpan untuk penyeragaman timing; dibuat sekali per proses. */
    private static ?string $dummyHash = null;

    public function login(LoginRequest $request): JsonResponse
    {
        $employee = Employee::where('nip', $request->input('nip'))->first();

        // Timing seragam: tetap jalankan Hash::check walau employee null.
        $passwordValid = $employee
            ? Hash::check($request->input('password'), $employee->password)
            : Hash::check($request->input('password'), self::dummyHash());

        if (! $employee || ! $passwordValid) {
            // Jejak audit untuk deteksi brute force / credential stuffing.
            AuditLog::record('auth.login_failed', [
                'employee_id' => $employee?->id,
                'severity'    => 'warning',
                'context'     => ['nip' => $request->input('nip')],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'NIP atau password salah.',
            ], 401);
        }

        // Status diperiksa SETELAH password terbukti benar. Urutan ini penting:
        // pesan spesifik di bawah hanya bisa dilihat oleh pemilik kredensial,
        // sehingga tidak bisa dipakai menebak NIP mana yang terdaftar.
        if (! $employee->isActive()) {
            AuditLog::record('auth.login_blocked', [
                'employee_id' => $employee->id,
                'severity'    => 'warning',
                'context'     => ['nip' => $employee->nip, 'status' => $employee->status],
            ]);

            $message = $employee->status === Employee::STATUS_PENDING
                ? 'Akun Anda belum disetujui admin.'
                : 'Akun Anda tidak aktif. Hubungi admin.';

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        // Batasi akumulasi sesi (maks 5 device aktif).
        if ($employee->tokens()->count() >= 5) {
            $employee->tokens()->oldest()->first()?->delete();
        }

        return $this->issueToken($employee, 'Login berhasil', 200);
    }

    /**
     * Rotasi token: hapus token saat ini, terbitkan yang baru.
     * Butuh token yang MASIH valid (belum kedaluwarsa) -> route auth:sanctum.
     */
    public function refresh(Request $request): JsonResponse
    {
        $employee = $request->user();

        // Cabut token yang sedang dipakai agar tidak bisa dipakai ulang.
        $request->user()->currentAccessToken()->delete();

        return $this->issueToken($employee, 'Token diperbarui', 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout dan token dihapus.',
        ], 200);
    }

    /**
     * Hash umpan yang dibuat dengan driver hashing yang sedang aktif.
     * Tidak boleh di-hardcode: hash bcrypt akan membuat Argon2IdHasher
     * melempar exception (500) saat NIP tidak ditemukan, sehingga justru
     * membocorkan keberadaan user lewat perbedaan status code.
     */
    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make('dummy-password-for-timing');
    }

    /**
     * Menerbitkan token baru + mengirim profil ringkas.
     * Masa berlaku diatur oleh config('sanctum.expiration').
     */
    private function issueToken(Employee $employee, string $message, int $status): JsonResponse
    {
        $token = $employee->createToken('mobile-absensi-token', self::MOBILE_ABILITIES)
            ->plainTextToken;

        $ttlMinutes = (int) config('sanctum.expiration', 480);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'token'      => $token,
                'expires_in' => $ttlMinutes * 60, // detik, untuk timer klien
                'karyawan' => [
                    'id'           => $employee->id,
                    'nip'          => $employee->nip,
                    'nama_lengkap' => $employee->nama_lengkap,
                    'jabatan'      => $employee->jabatan,
                    'status_wajah' => (bool) $employee->face_embedding,
                ],
            ],
        ], $status);
    }
}

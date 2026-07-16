<?php
/*
|==================================================================
| FITUR: Rate Limiting
| Mendefinisikan limiter 'login' (anti brute force per NIP+IP) dan 'api' (throttle umum).
|==================================================================
*/

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use App\Models\AuditLog;
use App\Observers\AuditLogObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
        $this->configurePasswordPolicy();

        // Alert otomatis untuk event audit kritis (SIEM/webhook).
        AuditLog::observe(AuditLogObserver::class);
    }

    /**
     * Kebijakan kata sandi tunggal (OWASP ASVS 2.1) untuk seluruh aplikasi:
     * admin men-set/reset password karyawan, dan karyawan mengganti sendiri.
     * Didefinisikan sekali di sini lalu dipakai lewat Password::defaults(),
     * agar aturannya tidak menyimpang antar-tempat saat kelak diubah.
     *
     * uncompromised() memeriksa password ke database kebocoran Have I Been
     * Pwned memakai k-anonymity (hanya 5 karakter awal hash yang dikirim).
     */
    protected function configurePasswordPolicy(): void
    {
        Password::defaults(fn () => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());
    }

    /**
     * Definisi rate limiter.
     * -------------------------------------------------------------------
     * 'login'  : anti brute force. Dikunci per kombinasi NIP + IP sehingga
     *            penyerang tidak bisa membanjiri satu akun, dan juga tidak
     *            bisa memutar banyak akun dari satu IP. 5 percobaan / menit.
     * 'api'    : throttle umum untuk semua endpoint (60 req/menit/pengguna).
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = strtolower((string) $request->input('nip')) . '|' . $request->ip();

            return [
                Limit::perMinute(5)->by($key)->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.',
                    ], 429);
                }),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                optional($request->user())->id ?: $request->ip()
            );
        });
    }
}

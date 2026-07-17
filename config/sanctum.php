<?php
/*
|==================================================================
| FITUR: Konfigurasi Sanctum
| Menetapkan masa berlaku (expiry) token agar token tidak valid selamanya.
|==================================================================
*/

use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Sanctum (HARDENED) — Token Expiry
|--------------------------------------------------------------------------
| 'expiration' (menit) membuat token TIDAK berlaku selamanya. Versi lama
| menerbitkan token tanpa masa berlaku -> jika token dicuri, valid selamanya
| (OWASP A07 / M3). Token tetap punya masa berlaku dan bisa di-rotasi lewat
| endpoint /refresh sebelum kedaluwarsa.
|
| Token yang sudah lewat 'expiration' otomatis dianggap invalid oleh Sanctum,
| namun barisnya tetap ada di DB -> jalankan `sanctum:prune-expired` terjadwal
| untuk membersihkan (lihat routes/console.php).
*/

return [

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),

    'guard' => ['web'],

    // 30 hari (43200 menit) — dipilih demi kenyamanan pengguna agar tidak
    // sering login ulang. Trade-off: jendela pemakaian token curian ikut
    // melebar dibanding rekomendasi 8 jam di SECURITY_AUDIT.md, jadi
    // pencabutan token saat logout & ganti password jadi makin penting.
    'expiration' => (int) env('SANCTUM_EXPIRATION', 43200),

    // Buffer refresh: token boleh di-refresh kapan pun selagi masih valid.
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies'      => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token'  => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];

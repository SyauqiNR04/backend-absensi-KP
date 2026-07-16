<?php
/*
|==================================================================
| FITUR: Konfigurasi CORS
| Allowlist origin eksplisit dari environment tanpa wildcard; credentials dimatikan untuk skema bearer token.
|==================================================================
*/

/*
|--------------------------------------------------------------------------
| CORS (HARDENED)
|--------------------------------------------------------------------------
| Prinsip: allowlist eksplisit, bukan wildcard.
|  - allowed_origins: JANGAN pakai '*'. Klien mobile Flutter native TIDAK
|    tunduk pada CORS (CORS hanya relevan untuk browser). Jika ada panel web
|    admin, daftarkan domain-nya secara eksplisit lewat env FRONTEND_URL.
|  - supports_credentials: true HANYA jika benar-benar memakai cookie/session
|    (mis. Sanctum SPA). Untuk token bearer murni, biarkan false.
|
| CATATAN: '*' pada allowed_origins + credentials=true DILARANG oleh spec dan
| membuka celah pencurian sesi lintas situs.
*/

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Allowlist eksplisit dari environment (pisahkan dengan koma).
    'allowed_origins' => array_filter(
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // Set true hanya untuk skema cookie-based (Sanctum SPA).
    'supports_credentials' => false,
];

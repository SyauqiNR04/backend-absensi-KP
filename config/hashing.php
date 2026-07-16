<?php
/*
|==================================================================
| FITUR: Konfigurasi Hashing
| Menggunakan Argon2id (memory-hard) sebagai driver hashing password default.
|==================================================================
*/

/*
|--------------------------------------------------------------------------
| Hashing (HARDENED) — Argon2id
|--------------------------------------------------------------------------
| Argon2id memenangkan Password Hashing Competition dan direkomendasikan
| OWASP: tahan terhadap serangan GPU/ASIC (memory-hard) sekaligus side-channel
| (varian 'id' = kombinasi Argon2i + Argon2d). Bcrypt tetap aman, namun
| Argon2id lebih future-proof untuk sistem baru.
|
| Parameter memory/time/threads mengikuti anjuran OWASP (min 19 MiB, t=2).
| Naikkan bila server kuat; ukur agar 1 hash ~250-500ms.
*/

return [

    'driver' => env('HASH_DRIVER', 'argon2id'),

    'bcrypt' => [
        'rounds'  => env('BCRYPT_ROUNDS', 12),
        'verify'  => true,
    ],

    'argon' => [
        'memory'  => (int) env('ARGON_MEMORY', 65536),   // 64 MiB
        'threads' => (int) env('ARGON_THREADS', 1),
        'time'    => (int) env('ARGON_TIME', 4),
        'verify'  => true,
    ],

    'rehash_on_login' => true,
];

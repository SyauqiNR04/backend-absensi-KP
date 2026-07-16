<?php
/*
|==================================================================
| FITUR: Scheduler
| Menjadwalkan pembersihan token Sanctum yang kedaluwarsa.
|==================================================================
*/

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduler
|--------------------------------------------------------------------------
| Bersihkan token yang sudah kedaluwarsa (lebih dari 24 jam lewat masa
| berlaku) agar tabel personal_access_tokens tidak menumpuk. Perintah ini
| disediakan Sanctum. Pastikan cron memanggil `php artisan schedule:run`
| setiap menit di server.
*/

Schedule::command('sanctum:prune-expired --hours=24')->daily();

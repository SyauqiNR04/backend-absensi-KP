<?php
/*
|==================================================================
| FITUR: Observer Audit Log
| Memicu alert otomatis saat event audit ber-severity 'critical'
| tercatat, tanpa mengubah kode pemanggil.
|==================================================================
*/

namespace App\Observers;

use App\Jobs\SendSecurityAlert;
use App\Models\AuditLog;

class AuditLogObserver
{
    public function created(AuditLog $log): void
    {
        // Hanya event kritis yang memicu alert real-time agar tidak berisik.
        if ($log->severity === 'critical') {
            SendSecurityAlert::dispatch($log->id);
        }
    }
}

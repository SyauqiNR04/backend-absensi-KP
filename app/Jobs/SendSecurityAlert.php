<?php
/*
|==================================================================
| FITUR: Job Pengiriman Alert
| Membungkus pengiriman alert keamanan ke antrian (queue) agar tidak
| memperlambat respons request pengguna.
|==================================================================
*/

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\Security\SecurityAlertService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSecurityAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $auditLogId)
    {
    }

    public function handle(SecurityAlertService $alerts): void
    {
        $log = AuditLog::find($this->auditLogId);
        if ($log) {
            $alerts->dispatchAlert($log);
        }
    }
}

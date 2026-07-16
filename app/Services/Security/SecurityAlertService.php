<?php
/*
|==================================================================
| FITUR: Alerting Keamanan (SIEM/Webhook)
| Mengirim event audit kritis ke saluran alert (webhook Slack/Teams)
| dan/atau log terstruktur JSON untuk di-ingest SIEM.
|==================================================================
*/

namespace App\Services\Security;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SecurityAlertService
 * -------------------------------------------------------------------------
 * Menutup separuh kedua OWASP A09: log saja tidak cukup — event kritis harus
 * MEMICU respons. Service ini mengirim notifikasi ke:
 *   1. Channel log 'security' (format JSON) -> mudah di-ingest SIEM
 *      (Wazuh, ELK, Splunk) via file/syslog shipper.
 *   2. Incoming webhook (Slack/Teams/Discord) untuk alert real-time ke tim.
 *
 * Dikontrol env agar aman saat belum dikonfigurasi (tidak error).
 */
class SecurityAlertService
{
    public function dispatchAlert(AuditLog $log): void
    {
        // (1) Selalu tulis ke channel log terstruktur untuk SIEM.
        Log::channel(config('logging.security_channel', 'stack'))->warning('security.audit', [
            'event'       => $log->event,
            'severity'    => $log->severity,
            'employee_id' => $log->employee_id,
            'ip'          => $log->ip_address,
            'context'     => $log->context,
            'at'          => optional($log->created_at)->toIso8601String(),
        ]);

        // (2) Webhook real-time (opsional).
        $webhook = config('services.security_alert.webhook', env('SECURITY_ALERT_WEBHOOK'));
        if (! $webhook) {
            return;
        }

        try {
            Http::timeout(5)->post($webhook, [
                'text' => sprintf(
                    "[%s] %s | employee=%s | ip=%s | %s",
                    strtoupper($log->severity),
                    $log->event,
                    $log->employee_id ?? '-',
                    $log->ip_address ?? '-',
                    json_encode($log->context)
                ),
            ]);
        } catch (\Throwable $e) {
            // Jangan biarkan kegagalan alert menjatuhkan request utama.
            Log::warning('SecurityAlert webhook failed', ['msg' => $e->getMessage()]);
        }
    }
}

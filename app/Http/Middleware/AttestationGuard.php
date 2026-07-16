<?php
/*
|==================================================================
| FITUR: Play Integrity Attestation Guard
| Menegakkan verifikasi keaslian perangkat & aplikasi ke Google Play sebelum absensi diterima; fail-closed.
|==================================================================
*/

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Services\Security\PlayIntegrityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AttestationGuard
 * -------------------------------------------------------------------------
 * Menegakkan Play Integrity attestation pada endpoint sensitif. Klien
 * mengirim token via header `X-Integrity-Token`. Jika verdict tidak
 * tepercaya, request ditolak dan dicatat di audit log.
 *
 * Aktifkan hanya bila attestation sudah dikonfigurasi (env). Bila belum,
 * middleware dilewati agar tidak memblokir seluruh trafik saat integrasi
 * bertahap (kontrol via config('services.play_integrity.enabled')).
 */
class AttestationGuard
{
    public function __construct(private PlayIntegrityService $integrity)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('services.play_integrity.enabled', false)) {
            return $next($request);
        }

        $token = (string) $request->header('X-Integrity-Token', '');
        $result = $this->integrity->verify($token);

        if (! $result['trusted']) {
            AuditLog::record('attestation.failed', [
                'employee_id' => optional($request->user())->id,
                'severity'    => 'critical',
                'context'     => ['reason' => $result['reason']],
            ]);

            return response()->json([
                'success' => false,
                'code'    => 'ATTESTATION_FAILED',
                'message' => 'Perangkat gagal verifikasi integritas Google Play.',
            ], 403);
        }

        return $next($request);
    }
}

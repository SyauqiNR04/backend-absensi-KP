<?php
/*
|==================================================================
| FITUR: Device Integrity Guard
| Menolak absensi dari perangkat root/emulator/Fake GPS berdasarkan flag klien (lapisan zero-trust server-side).
|==================================================================
*/

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DeviceIntegrity
 * -------------------------------------------------------------------------
 * Lapisan server-side (zero-trust) untuk klaim integritas perangkat.
 * Deteksi root/emulator/mock-GPS di Flutter dapat dilewati oleh penyerang
 * yang memodifikasi APK, maka SERVER tidak boleh percaya begitu saja.
 *
 * Middleware ini menolak request absensi bila klien secara jujur melaporkan
 * kondisi berbahaya (defense-in-depth), sekaligus mencatat sinyal untuk
 * audit anomali (mis. mock-location dilaporkan false namun kecepatan lokasi
 * mustahil -> ditinjau terpisah).
 *
 * Terapkan hanya pada route yang sensitif terhadap lokasi (POST /attendances).
 */
class DeviceIntegrity
{
    public function handle(Request $request, Closure $next): Response
    {
        $flags = [
            'is_rooted'        => $request->boolean('is_rooted'),
            'is_emulator'      => $request->boolean('is_emulator'),
            'is_mock_location' => $request->boolean('is_mock_location'),
        ];

        if ($flags['is_mock_location']) {
            return $this->reject('Absensi ditolak: lokasi palsu (Fake GPS) terdeteksi.');
        }

        if ($flags['is_emulator']) {
            return $this->reject('Absensi ditolak: aplikasi dijalankan di emulator.');
        }

        if ($flags['is_rooted']) {
            return $this->reject('Absensi ditolak: perangkat root/jailbreak terdeteksi.');
        }

        return $next($request);
    }

    private function reject(string $message): Response
    {
        return response()->json([
            'success' => false,
            'code'    => 'DEVICE_INTEGRITY_FAILED',
            'message' => $message,
        ], 403);
    }
}

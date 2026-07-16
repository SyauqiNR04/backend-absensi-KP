<?php
/*
|==================================================================
| FITUR: Verifikasi Play Integrity
| Memvalidasi token attestation Android ke server Google dan memeriksa verdict integritas perangkat & aplikasi.
|==================================================================
*/

namespace App\Services\Security;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PlayIntegrityService
 * -------------------------------------------------------------------------
 * Verifikasi SERVER-SIDE atas token Play Integrity (Android) — attestation
 * yang TIDAK bisa dipalsukan hanya dengan me-repackage APK, karena verdict
 * ditandatangani oleh Google. Ini menutup keterbatasan deteksi root/emulator
 * di klien (yang bisa di-bypass).
 *
 * Alur: aplikasi meminta integrity token ke Google Play -> mengirimnya ke
 * server -> server memanggil `decodeIntegrityToken` -> memeriksa verdict.
 *
 * PRASYARAT:
 *   composer require google/apiclient
 *   - Buat service account di Google Cloud, aktifkan Play Integrity API.
 *   - Simpan JSON credential; set path di env GOOGLE_APPLICATION_CREDENTIALS.
 *   - Set PLAY_INTEGRITY_PACKAGE (applicationId Android, mis. com.perusahaan.absensi).
 *
 * Untuk iOS gunakan Apple App Attest / DeviceCheck (di luar cakupan kelas ini).
 */
class PlayIntegrityService
{
    private string $packageName;

    public function __construct()
    {
        $this->packageName = (string) config('services.play_integrity.package', env('PLAY_INTEGRITY_PACKAGE', ''));
    }

    /**
     * @return array{trusted: bool, verdict: array|null, reason: string|null}
     */
    public function verify(string $integrityToken): array
    {
        if ($integrityToken === '' || $this->packageName === '') {
            return ['trusted' => false, 'verdict' => null, 'reason' => 'Token/konfigurasi tidak lengkap.'];
        }

        try {
            $accessToken = $this->accessToken();

            $response = Http::withToken($accessToken)
                ->timeout(8)
                ->post(
                    "https://playintegrity.googleapis.com/v1/{$this->packageName}:decodeIntegrityToken",
                    ['integrity_token' => $integrityToken]
                );

            if ($response->failed()) {
                return ['trusted' => false, 'verdict' => null, 'reason' => 'Gagal memverifikasi ke Google.'];
            }

            $payload = $response->json('tokenPayloadExternal', []);
            $deviceVerdict = data_get($payload, 'deviceIntegrity.deviceRecognitionVerdict', []);
            $appVerdict = data_get($payload, 'appIntegrity.appRecognitionVerdict');

            // Kebijakan: perangkat harus MEETS_DEVICE_INTEGRITY dan aplikasi asli.
            $deviceOk = in_array('MEETS_DEVICE_INTEGRITY', (array) $deviceVerdict, true);
            $appOk = $appVerdict === 'PLAY_RECOGNIZED';

            if (! $deviceOk) {
                return ['trusted' => false, 'verdict' => $payload, 'reason' => 'Integritas perangkat tidak terpenuhi.'];
            }
            if (! $appOk) {
                return ['trusted' => false, 'verdict' => $payload, 'reason' => 'Aplikasi tidak dikenali Play (kemungkinan dimodifikasi).'];
            }

            return ['trusted' => true, 'verdict' => $payload, 'reason' => null];
        } catch (\Throwable $e) {
            Log::warning('PlayIntegrity verify error', ['msg' => $e->getMessage()]);
            // Fail-closed: bila verifikasi tak dapat dilakukan, jangan percaya.
            return ['trusted' => false, 'verdict' => null, 'reason' => 'Verifikasi integritas gagal.'];
        }
    }

    /** Access token OAuth2 dari service account (di-cache hingga mendekati exp). */
    private function accessToken(): string
    {
        return Cache::remember('play_integrity_access_token', 3300, function () {
            $client = new GoogleClient();
            $client->useApplicationDefaultCredentials();
            $client->addScope('https://www.googleapis.com/auth/playintegrity');
            $token = $client->fetchAccessTokenWithAssertion();
            return $token['access_token'] ?? '';
        });
    }
}

<?php
/*
|==================================================================
| FITUR: Deteksi Anomali Lokasi
| Mendeteksi impossible-travel (kecepatan mustahil) antar absensi berurutan sebagai sinyal Fake GPS/berbagi akun.
|==================================================================
*/

namespace App\Services\Security;

use App\Models\Attendance;
use Carbon\Carbon;

/**
 * LocationAnomalyDetector
 * -------------------------------------------------------------------------
 * Mendeteksi "impossible travel": kecepatan tersirat antara dua absensi
 * berurutan yang melebihi batas fisik manusiawi -> indikasi Fake GPS atau
 * berbagi akun, meski flag mock-location klien mengaku 'false'.
 *
 * Ini adalah kontrol perilaku (behavioral) yang melengkapi deteksi teknis:
 * penyerang mungkin bisa memalsukan satu titik, tapi sulit menjaga
 * konsistensi kecepatan lintas waktu.
 */
class LocationAnomalyDetector
{
    /** Ambang kecepatan wajar (km/jam). > ini dianggap mustahil. */
    private const MAX_PLAUSIBLE_KMH = 900.0; // ~kecepatan pesawat komersial

    public function __construct(private float $maxKmh = self::MAX_PLAUSIBLE_KMH)
    {
    }

    /**
     * @return array{is_anomaly: bool, speed_kmh: float|null, reason: string|null}
     */
    public function evaluate(int $employeeId, float $lat, float $lon, Carbon $at): array
    {
        $previous = Attendance::where('employee_id', $employeeId)
            ->orderByDesc('waktu_absen')
            ->first();

        if (! $previous || $previous->latitude === null || $previous->longitude === null) {
            return ['is_anomaly' => false, 'speed_kmh' => null, 'reason' => null];
        }

        $meters = $this->haversine(
            (float) $previous->latitude, (float) $previous->longitude, $lat, $lon
        );
        $seconds = max(1, $at->diffInSeconds(Carbon::parse($previous->waktu_absen)));
        $speedKmh = ($meters / $seconds) * 3.6;

        if ($speedKmh > $this->maxKmh) {
            return [
                'is_anomaly' => true,
                'speed_kmh'  => round($speedKmh, 2),
                'reason'     => 'Kecepatan perpindahan mustahil (' . round($speedKmh) . ' km/jam).',
            ];
        }

        return ['is_anomaly' => false, 'speed_kmh' => round($speedKmh, 2), 'reason' => null];
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}

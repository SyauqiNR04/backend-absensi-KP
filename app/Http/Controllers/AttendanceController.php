<?php
/*
|==================================================================
| FITUR: Absensi
| Submit absensi aman (identitas dari token, bukan body), geo-fence, deteksi anomali lokasi, audit log, dan riwayat anti-IDOR.
|==================================================================
*/

namespace App\Http\Controllers;

use App\Http\Requests\Api\StoreAttendanceRequest;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\Security\LocationAnomalyDetector;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

/**
 * AttendanceController (HARDENED + Phase 3: anomaly detection & audit log)
 * -------------------------------------------------------------------------
 *  - Identitas dari token ($request->user()), bukan body (anti-spoofing).
 *  - IDOR guard pada history().
 *  - Eloquent parameter-binding (anti-SQLi).
 *  - Foto: nama acak, disk privat.
 *  - Phase 3: deteksi impossible-travel + pencatatan audit setiap event.
 */
class AttendanceController extends Controller
{
    public function __construct(private LocationAnomalyDetector $anomalyDetector)
    {
    }

    private function hitungJarak($lat1, $lon1, $lat2, $lon2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $employee = $request->user();
        $waktuSekarang = Carbon::now('Asia/Jakarta');
        $lat = (float) $request->input('latitude');
        $lon = (float) $request->input('longitude');

        // Cegah duplikasi harian.
        $sudahAbsen = Attendance::where('employee_id', $employee->id)
            ->whereDate('waktu_absen', $waktuSekarang->toDateString())
            ->exists();
        if ($sudahAbsen) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah melakukan absensi hari ini.',
            ], 409);
        }

        $setting = Setting::first();
        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Sistem belum dikonfigurasi oleh Admin.',
            ], 500);
        }

        // Geo-fence.
        $jarak = $this->hitungJarak($setting->latitude, $setting->longitude, $lat, $lon);
        if ($jarak > $setting->radius_meter) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal! Anda berada di luar area kantor. Jarak Anda: ' . round($jarak) . ' meter.',
            ], 403);
        }

        // Phase 3: deteksi impossible-travel (behavioral anti Fake GPS).
        $anomaly = $this->anomalyDetector->evaluate($employee->id, $lat, $lon, $waktuSekarang);
        if ($anomaly['is_anomaly']) {
            AuditLog::record('location.anomaly', [
                'employee_id' => $employee->id,
                'severity'    => 'critical',
                'context'     => [
                    'speed_kmh' => $anomaly['speed_kmh'],
                    'lat'       => $lat,
                    'lon'       => $lon,
                ],
            ]);
            return response()->json([
                'success' => false,
                'code'    => 'LOCATION_ANOMALY',
                'message' => 'Absensi ditinjau: ' . $anomaly['reason'],
            ], 422);
        }

        $jamMasuk = Carbon::parse($setting->jam_masuk, 'Asia/Jakarta');
        $status = $waktuSekarang->gt($jamMasuk) ? 'terlambat' : 'hadir';

        // Foto: nama acak, disk privat.
        $fotoPath = $request->file('foto')->store('absensi', 'private');

        Attendance::create([
            'employee_id' => $employee->id,
            'waktu_absen' => $waktuSekarang,
            'status'      => $status,
            'latitude'    => $lat,
            'longitude'   => $lon,
            'foto_bukti'  => $fotoPath,
        ]);

        AuditLog::record('attendance.created', [
            'employee_id' => $employee->id,
            'severity'    => 'info',
            'context'     => ['status' => $status, 'jarak_meter' => round($jarak)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absensi berhasil direkam!',
            'data' => [
                'status'      => ucfirst($status),
                'waktu'       => $waktuSekarang->format('H:i:s') . ' WIB',
                'jarak_meter' => round($jarak),
            ],
        ], 201);
    }

    public function history(): JsonResponse
    {
        $employee = request()->user();

        $riwayat = Attendance::where('employee_id', $employee->id)
            ->orderByDesc('waktu_absen')
            ->limit(30)
            ->get();

        return response()->json(['success' => true, 'data' => $riwayat], 200);
    }
}

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
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Submit absensi. Submit PERTAMA di hari itu = absen MASUK (buat record
     * baru). Submit KEDUA = absen PULANG (update record yang sama dengan
     * waktu_pulang + hitung total jam kerja). Submit KETIGA dst ditolak.
     */
    public function store(StoreAttendanceRequest $request): JsonResponse
    {
        $employee = $request->user();
        $waktuSekarang = Carbon::now('Asia/Jakarta');
        $lat = (float) $request->input('latitude');
        $lon = (float) $request->input('longitude');

        $setting = Setting::first();
        if (! $setting) {
            return response()->json([
                'success' => false,
                'message' => 'Sistem belum dikonfigurasi oleh Admin.',
            ], 500);
        }

        // Geo-fence (berlaku untuk absen masuk maupun pulang).
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

        $absenHariIni = Attendance::where('employee_id', $employee->id)
            ->whereDate('waktu_absen', $waktuSekarang->toDateString())
            ->first();

        // Foto: nama acak, disk privat (storage/app/private, tidak bisa diakses lewat URL publik).
        $fotoPath = $request->file('foto')->store('absensi', 'local');

        if (! $absenHariIni) {
            return $this->absenMasuk($employee, $waktuSekarang, $lat, $lon, $jarak, $fotoPath, $setting);
        }

        if ($absenHariIni->waktu_pulang) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah menyelesaikan absensi hari ini (masuk & pulang).',
            ], 409);
        }

        return $this->absenPulang($absenHariIni, $waktuSekarang, $lat, $lon, $jarak, $fotoPath);
    }

    private function absenMasuk(
        $employee,
        Carbon $waktuSekarang,
        float $lat,
        float $lon,
        float $jarak,
        string $fotoPath,
        Setting $setting,
    ): JsonResponse {
        $jamMasuk = Carbon::parse($setting->jam_masuk, 'Asia/Jakarta');
        $status = $waktuSekarang->gt($jamMasuk) ? 'terlambat' : 'hadir';

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
            'message' => 'Absen masuk berhasil direkam!',
            'data' => [
                'type'        => 'masuk',
                'status'      => ucfirst($status),
                'waktu'       => $waktuSekarang->format('H:i:s') . ' WIB',
                'jarak_meter' => round($jarak),
            ],
        ], 201);
    }

    private function absenPulang(
        Attendance $absenHariIni,
        Carbon $waktuSekarang,
        float $lat,
        float $lon,
        float $jarak,
        string $fotoPath,
    ): JsonResponse {
        $absenHariIni->update([
            'waktu_pulang'     => $waktuSekarang,
            'foto_pulang'      => $fotoPath,
            'latitude_pulang'  => $lat,
            'longitude_pulang' => $lon,
        ]);

        $menitKerja = (int) round($absenHariIni->waktu_absen->diffInMinutes($waktuSekarang));
        $jamKerja = intdiv($menitKerja, 60);
        $sisaMenit = $menitKerja % 60;

        AuditLog::record('attendance.checkout', [
            'employee_id' => $absenHariIni->employee_id,
            'severity'    => 'info',
            'context'     => ['menit_kerja' => $menitKerja, 'jarak_meter' => round($jarak)],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absen pulang berhasil direkam!',
            'data' => [
                'type'              => 'pulang',
                'waktu'             => $waktuSekarang->format('H:i:s') . ' WIB',
                'jarak_meter'       => round($jarak),
                'total_menit_kerja' => $menitKerja,
                'total_jam_kerja'   => sprintf('%dh %02dm', $jamKerja, $sisaMenit),
            ],
        ], 200);
    }

    /**
     * Status absensi HARI INI milik pemilik token -- dipakai dashboard klien
     * agar tahu tombol berikutnya "Absen Masuk" atau "Absen Pulang", dan
     * bisa menampilkan arrival time / total jam kerja yang sinkron dengan
     * data asli (bukan placeholder statis).
     */
    public function today(): JsonResponse
    {
        $employee = request()->user();
        $sekarang = Carbon::now('Asia/Jakarta');

        $absen = Attendance::where('employee_id', $employee->id)
            ->whereDate('waktu_absen', $sekarang->toDateString())
            ->first();

        $setting = Setting::first();

        return response()->json([
            'success' => true,
            'data' => [
                'server_time' => $sekarang->toIso8601String(),
                'jam_masuk_kantor'  => $setting?->jam_masuk,
                'jam_pulang_kantor' => $setting?->jam_pulang,
                'attendance' => $absen,
            ],
        ], 200);
    }

    /**
     * Menyajikan foto referensi wajah milik PEMILIK TOKEN sendiri (bukan dari
     * parameter URL) -- sama seperti history(), identitas selalu dari token
     * agar tidak ada IDOR yang membocorkan foto karyawan lain.
     */
    public function referencePhoto(): JsonResponse|StreamedResponse
    {
        $employee = request()->user();

        if (! $employee->foto_referensi || ! Storage::disk('local')->exists($employee->foto_referensi)) {
            return response()->json([
                'success' => false,
                'message' => 'Foto referensi belum diset oleh admin.',
            ], 404);
        }

        return Storage::disk('local')->response($employee->foto_referensi, null, [
            'Cache-Control' => 'no-store',
        ]);
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

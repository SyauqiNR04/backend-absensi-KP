<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi input yang dikirim dari perangkat
        $request->validate([
            'nip' => 'required|exists:employees,nip',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        // 2. Ambil data karyawan berdasarkan NIP dan data koordinat kantor pusat
        $employee = Employee::where('nip', $request->nip)->first();
        $setting = Setting::first(); 

        if (!$setting) {
            return response()->json(['message' => 'Pengaturan lokasi kantor belum diatur di database!'], 404);
        }

        // 3. Rumus Haversine (Menghitung jarak antara dua koordinat dalam satuan Meter)
        $earthRadius = 6371000; // Jari-jari bumi dalam meter
        $latFrom = deg2rad($request->latitude);
        $lonFrom = deg2rad($request->longitude);
        $latTo = deg2rad($setting->latitude);
        $lonTo = deg2rad($setting->longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        $jarak_meter = $angle * $earthRadius;

        // 4. Validasi Radius GPS
        if ($jarak_meter > $setting->radius_meter) {
            // Hitung seberapa jauh dia meleset dari batas radius
            $selisih_jarak = $jarak_meter - $setting->radius_meter;

            // Jika selisihnya 1000 meter atau lebih, ubah ke KM
            if ($selisih_jarak >= 1000) {
                // Konversi ke KM dengan 1 angka di belakang koma (contoh: 4,3 KM)
                $angka_km = number_format($selisih_jarak / 1000, 1, ',', '.');
                $teks_jarak = $angka_km . ' KM';
            } else {
                // Jika masih di bawah 1000 meter, tetap gunakan Meter
                $teks_jarak = round($selisih_jarak) . ' Meter';
            }

            return response()->json([
                'message' => 'Gagal Absen!',
                'detail' => "Anda berada $teks_jarak di luar jangkauan wilayah kantor."
            ], 403);
        }

        // 5. Simpan Data Absensi jika posisi berada di dalam radius
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'waktu_absen' => now(),
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'status' => 'hadir'
        ]);

        return response()->json([
            'message' => 'Absensi berhasil dicatat!',
            'jarak_anda' => round($jarak_meter) . ' meter',
            'data' => $attendance
        ], 201);
    }
    
    public function history($nip)
    {
        // 1. Cari data karyawan berdasarkan NIP
        $employee = Employee::where('nip', $nip)->first();

        if (!$employee) {
            return response()->json(['message' => 'Karyawan tidak ditemukan!'], 404);
        }

        // 2. Ambil semua absensi milik karyawan tersebut, urutkan dari yang terbaru
        $history = Attendance::where('employee_id', $employee->id)
                    ->orderBy('waktu_absen', 'desc')
                    ->get();

        return response()->json([
            'message' => 'Berhasil mengambil riwayat absensi',
            'data' => $history
        ], 200);
    }
}

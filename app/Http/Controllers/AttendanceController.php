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
            'image' => 'nullable|string', 
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
            $selisih_jarak = $jarak_meter - $setting->radius_meter;

            if ($selisih_jarak >= 1000) {
                $angka_km = number_format($selisih_jarak / 1000, 1, ',', '.');
                $teks_jarak = $angka_km . ' KM';
            } else {
                $teks_jarak = round($selisih_jarak) . ' Meter';
            }

            return response()->json([
                'message' => 'Gagal Absen!',
                'detail' => "Anda berada $teks_jarak di luar jangkauan wilayah kantor."
            ], 403);
        }

        // ---> PERBAIKAN ERROR 1: Deklarasi awal variabel $imagePath <---
        $imagePath = null;
        
        if ($request->has('image') && $request->image != "") {
            $image_base64 = base64_decode($request->image);
            $fileName = 'foto_' . $request->nip . '_' . time() . '.jpg';
            $path = 'attendances/' . $fileName;
            Storage::disk('public')->put($path, $image_base64);
            $imagePath = $path; // Variabel diisi jika foto berhasil diproses
        }

        // 5. Simpan Data Absensi jika posisi berada di dalam radius
        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'waktu_absen' => now(),
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'status' => 'hadir',
            'foto_bukti' => $imagePath // Data disimpan ke kolom 'foto_bukti'
        ]);

        return response()->json([
            'message' => 'Absensi berhasil dicatat!',
            'jarak_anda' => round($jarak_meter) . ' meter',
            'data' => $attendance
        ], 201);
    }
    
    // ---> PERBAIKAN ERROR 2: Menambahkan tipe data 'string' <---
    public function history(string $nip)
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
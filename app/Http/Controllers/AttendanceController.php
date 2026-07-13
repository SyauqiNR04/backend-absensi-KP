<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    // Fungsi matematika (Haversine Formula) untuk menghitung jarak antara 2 koordinat GPS dalam hitungan meter
    private function hitungJarak($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Radius bumi dalam meter
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) + 
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    // Fungsi menerima kiriman absen dari aplikasi mobile
    public function store(Request $request)
    {
        // 1. Validasi Input dari HP (Harus ada NIP, Koordinat, dan File Foto)
        $request->validate([
            'nip' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'foto' => 'required|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        // 2. Ambil ID Karyawan berdasarkan NIP
        $employee = DB::table('employees')->where('nip', $request->nip)->first();
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Karyawan tidak terdaftar.'], 404);
        }

        $waktuSekarang = Carbon::now('Asia/Jakarta');

        // 3. Cek apakah karyawan sudah absen hari ini agar tidak dobel
        $sudahAbsen = DB::table('attendances')
            ->where('employee_id', $employee->id)
            ->whereDate('waktu_absen', $waktuSekarang->toDateString())
            ->exists();

        if ($sudahAbsen) {
            return response()->json(['success' => false, 'message' => 'Anda sudah melakukan absensi hari ini.'], 400);
        }

        // 4. Ambil Pengaturan Geo-Fence & Jam Kerja dari Database
        $setting = DB::table('settings')->first();
        if (!$setting) {
            return response()->json(['success' => false, 'message' => 'Sistem belum dikonfigurasi oleh Admin.'], 500);
        }

        // 5. Validasi Jarak (Geo-Fence)
        $jarak = $this->hitungJarak($setting->latitude, $setting->longitude, $request->latitude, $request->longitude);
        if ($jarak > $setting->radius_meter) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal! Anda berada di luar area kantor. Jarak Anda: ' . round($jarak) . ' meter.'
            ], 403);
        }

        // 6. Penentuan Status Keterlambatan
        $jamMasuk = Carbon::parse($setting->jam_masuk, 'Asia/Jakarta');
        // Jika waktu absen lebih besar (melewati) jam masuk = Terlambat
        $status = $waktuSekarang->gt($jamMasuk) ? 'terlambat' : 'hadir';

        // 7. Simpan Foto Selfie ke Server
        $fotoPath = null;
        if ($request->hasFile('foto')) {
            // Akan otomatis masuk ke folder storage/app/public/absensi
            $fotoPath = $request->file('foto')->store('absensi', 'public');
        }

        // 8. Masukkan Data ke Database Utama
        DB::table('attendances')->insert([
            'employee_id' => $employee->id,
            'waktu_absen' => $waktuSekarang,
            'status' => $status,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'foto_bukti' => $fotoPath,
            'created_at' => $waktuSekarang,
            'updated_at' => $waktuSekarang,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Absensi berhasil direkam!',
            'data' => [
                'status' => ucfirst($status),
                'waktu' => $waktuSekarang->format('H:i:s WIB'),
                'jarak_meter' => round($jarak)
            ]
        ], 200);
    }

    // Fungsi tambahan untuk mengirim data riwayat ke HP karyawan
    public function history($nip)
    {
        $employee = DB::table('employees')->where('nip', $nip)->first();
        if (!$employee) return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);

        $riwayat = DB::table('attendances')
            ->where('employee_id', $employee->id)
            ->orderBy('waktu_absen', 'desc')
            ->limit(30) // Tampilkan batas 30 hari terakhir di HP
            ->get();

        return response()->json(['success' => true, 'data' => $riwayat], 200);
    }
}
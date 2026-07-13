<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;

class AuthController extends Controller
{
    // Fungsi Login Aplikasi Mobile
    public function login(Request $request)
    {
        // Memastikan aplikasi mengirim data NIP
        $request->validate([
            'nip' => 'required|string'
        ]);

        // Mencari karyawan berdasarkan NIP
        $employee = Employee::where('nip', $request->nip)->first();

        // Jika NIP tidak terdaftar di database
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'NIP tidak ditemukan dalam sistem.'
            ], 404);
        }

        // Membuat Token Sanctum untuk sesi login karyawan ini
        $token = $employee->createToken('mobile-absensi-token')->plainTextToken;

        // Mengirimkan Token dan Data Profil ke Aplikasi Mobile
        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'token' => $token,
                'karyawan' => [
                    'id' => $employee->id,
                    'nip' => $employee->nip,
                    'nama_lengkap' => $employee->nama_lengkap,
                    'jabatan' => $employee->jabatan,
                    'status_wajah' => $employee->face_embedding ? true : false
                ]
            ]
        ], 200);
    }

    // Fungsi Logout Aplikasi Mobile (Menghapus Token)
    public function logout(Request $request)
    {
        // Menghapus token yang sedang digunakan
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout dan token dihapus.'
        ], 200);
    }
}
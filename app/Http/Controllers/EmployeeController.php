<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi keamanan data yang masuk
        $request->validate([
            'nip' => 'required|unique:employees,nip',
            'nama_lengkap' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
            'face_embedding' => 'nullable|array', // Data wajah dari AI
        ]);

        // 2. Simpan ke database
        $employee = Employee::create($request->all());

        // 3. Kirim respon sukses kembali ke Flutter/Frontend
        return response()->json([
            'message' => 'Karyawan berhasil didaftarkan!',
            'data' => $employee
        ], 201);
    }
}
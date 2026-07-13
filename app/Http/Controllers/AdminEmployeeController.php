<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminEmployeeController
{
    // 1. READ: Menampilkan daftar seluruh karyawan
    public function index()
    {
        $employees = DB::table('employees')->orderBy('id', 'desc')->get();
        return view('admin.employees.index', compact('employees'));
    }

    // 2. CREATE: Menampilkan formulir tambah karyawan baru
    public function create()
    {
        return view('admin.employees.create');
    }

    // 3. STORE: Menangkap data dari formulir dan menyimpannya ke database
    public function store(Request $request)
    {
        $request->validate([
            'nip' => 'required|string|max:50|unique:employees,nip',
            'nama_lengkap' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
        ]);

        DB::table('employees')->insert([
            'nip' => $request->nip,
            'nama_lengkap' => $request->nama_lengkap,
            'jabatan' => $request->jabatan,
            'created_at' => Carbon::now('Asia/Jakarta'),
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return redirect('/admin/employees')->with('success', 'Data karyawan baru berhasil ditambahkan!');
    }

    // 4. EDIT: Menampilkan formulir edit berdasarkan ID karyawan
    public function edit($id)
    {
        $employee = DB::table('employees')->where('id', $id)->first();
        
        if (!$employee) {
            return redirect('/admin/employees')->with('error', 'Data karyawan tidak ditemukan.');
        }

        return view('admin.employees.edit', compact('employee'));
    }

    // 5. UPDATE: Menyimpan perubahan data dari formulir edit ke database
    public function update(Request $request, $id)
    {
        $request->validate([
            'nip' => 'required|string|max:50|unique:employees,nip,' . $id,
            'nama_lengkap' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
        ]);

        DB::table('employees')->where('id', $id)->update([
            'nip' => $request->nip,
            'nama_lengkap' => $request->nama_lengkap,
            'jabatan' => $request->jabatan,
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return redirect('/admin/employees')->with('success', 'Data karyawan berhasil diperbarui!');
    }

    // 6. DELETE: Menghapus data karyawan dari database
    public function destroy($id)
    {
        DB::table('employees')->where('id', $id)->delete();
        return redirect('/admin/employees')->with('success', 'Data karyawan berhasil dihapus!');
    }
}
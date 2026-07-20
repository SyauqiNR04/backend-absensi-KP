<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    // Mengizinkan semua kolom diisi melalui API
    protected $guarded = [];

    /**
     * Path penyimpanan foto tidak pernah dibutuhkan aplikasi klien: fotonya
     * ada di disk privat dan hanya bisa diambil lewat endpoint ber-token.
     * Mengirimkan path-nya hanya membocorkan struktur direktori server.
     *
     * Catatan: $hidden hanya memengaruhi serialisasi ke JSON. Akses properti
     * di sisi server (mis. $absen->foto_bukti pada panel admin) tetap normal.
     */
    protected $hidden = ['foto_bukti', 'foto_pulang'];

    // waktu_absen/waktu_pulang perlu jadi Carbon agar bisa dihitung durasinya
    // (mis. diffInMinutes() untuk total jam kerja).
    protected $casts = [
        'waktu_absen'  => 'datetime',
        'waktu_pulang' => 'datetime',
    ];

    // Relasi balik ke tabel Karyawan (Satu absensi dimiliki oleh satu karyawan)
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    // Bukti verifikasi wajah: hingga dua baris per absensi (masuk & pulang).
    public function evidences()
    {
        return $this->hasMany(AttendanceEvidence::class);
    }
}
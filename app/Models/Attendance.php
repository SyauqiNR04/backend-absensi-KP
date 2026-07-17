<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    // Mengizinkan semua kolom diisi melalui API
    protected $guarded = [];

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
}
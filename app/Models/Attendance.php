<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    // Mengizinkan semua kolom diisi melalui API
    protected $guarded = []; 

    // Relasi balik ke tabel Karyawan (Satu absensi dimiliki oleh satu karyawan)
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
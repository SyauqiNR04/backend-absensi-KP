<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Mengubahnya menjadi tipe Authenticatable
use Laravel\Sanctum\HasApiTokens; // Syarat mutlak untuk API

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory;

    /** Boleh login & absen. */
    public const STATUS_ACTIVE = 'active';

    /** Resign/diberhentikan: login ditolak, riwayat absensi tetap disimpan. */
    public const STATUS_INACTIVE = 'inactive';

    /** Terdaftar tapi belum disetujui admin. */
    public const STATUS_PENDING = 'pending';

    protected $table = 'employees';

    // Mengizinkan semua kolom diisi secara massal (mass assignment)
    protected $guarded = ['id'];

    /**
     * GET /api/user mengembalikan model ini apa adanya. Tanpa daftar ini,
     * hash password dan face_embedding (template biometrik) ikut terkirim ke
     * aplikasi klien — data yang tidak pernah dibutuhkan UI dan berbahaya bila
     * respons ter-log atau ter-cache.
     */
    protected $hidden = ['password', 'remember_token', 'face_embedding'];

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Menonaktifkan karyawan sekaligus mencabut seluruh tokennya, sehingga
     * sesi yang sedang berjalan di HP ikut mati. Tanpa pencabutan token,
     * karyawan yang sudah resign masih bisa absen sampai tokennya kedaluwarsa.
     */
    public function deactivate(): void
    {
        $this->forceFill(['status' => self::STATUS_INACTIVE])->save();
        $this->tokens()->delete();
    }
}
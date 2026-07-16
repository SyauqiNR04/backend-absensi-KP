<?php
/*
|==================================================================
| FITUR: Migrasi Status Karyawan
| Menambahkan kolom status (pending/active/inactive) pada tabel employees.
|==================================================================
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan status siklus hidup karyawan.
 * -------------------------------------------------------------------------
 * Sebelumnya tidak ada cara menonaktifkan karyawan: satu-satunya jalan
 * mengeluarkan karyawan yang resign adalah menghapus barisnya, dan karena
 * attendances memakai onDelete('cascade'), seluruh riwayat absensinya ikut
 * terhapus permanen -> bukti penggajian hilang.
 *
 * Nilai:
 *   active   : boleh login & absen (default untuk data lama).
 *   inactive : resign/diberhentikan. Riwayat absensi tetap utuh, login ditolak.
 *   pending   : terdaftar tapi belum disetujui admin. Belum dipakai selama
 *               registrasi mandiri ditutup, namun tersedia bila kelak dibuka
 *               tanpa perlu mengubah skema lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('status', 20)
                ->default('active')
                ->after('jabatan')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

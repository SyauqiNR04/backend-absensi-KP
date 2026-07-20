<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * foto_bukti: nullable -> wajib diisi
 * -------------------------------------------------------------------------
 * StoreAttendanceRequest sudah MEWAJIBKAN berkas foto pada setiap absensi,
 * sehingga jalur API tidak pernah menghasilkan baris tanpa foto. Yang masih
 * terbuka adalah skemanya: kolom nullable mengizinkan penyisipan manual
 * (lewat SQL langsung, seeder, atau perkakas basis data) menghasilkan absensi
 * tanpa bukti foto -- persis jenis baris yang baru saja dibersihkan.
 *
 * Batasan ini memindahkan aturan "absensi harus punya bukti foto" dari
 * kesepakatan di lapisan aplikasi menjadi jaminan di lapisan basis data.
 *
 * foto_pulang SENGAJA dibiarkan nullable: karyawan yang sudah absen masuk
 * tetapi belum absen pulang adalah keadaan yang sah dan umum. Mewajibkannya
 * akan membuat absen masuk mustahil disimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        // PENJAGA. MySQL di luar mode ketat tidak menolak baris NULL saat kolom
        // dijadikan NOT NULL -- ia diam-diam mengubahnya menjadi string kosong.
        // Hasilnya baris yang tampak punya foto padahal path-nya kosong: rusak
        // dengan cara yang jauh lebih sulit ditemukan daripada NULL.
        // Lebih baik migrasi berhenti dan meminta datanya dibereskan dulu.
        $tanpaFoto = DB::table('attendances')
            ->where(function ($q) {
                $q->whereNull('foto_bukti')->orWhere('foto_bukti', '');
            })
            ->count();

        if ($tanpaFoto > 0) {
            throw new RuntimeException(
                "Migrasi dihentikan: {$tanpaFoto} baris attendances masih tanpa foto_bukti. " .
                'Periksa dan bereskan baris tersebut lebih dulu (cadangkan sebelum menghapus), ' .
                'lalu jalankan ulang migrasi ini.'
            );
        }

        Schema::table('attendances', function (Blueprint $table) {
            // Definisi diulang persis seperti aslinya (string = varchar 255);
            // change() mengganti seluruh definisi kolom, bukan menambahinya,
            // sehingga tipe yang tidak disebutkan akan ikut berubah.
            $table->string('foto_bukti')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('foto_bukti')->nullable()->change();
        });
    }
};

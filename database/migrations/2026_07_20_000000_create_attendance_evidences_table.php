<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * attendance_evidences
 * -------------------------------------------------------------------------
 * Bukti verifikasi wajah untuk setiap event absensi.
 *
 * LATAR: pencocokan wajah dan liveness berjalan DI APLIKASI, bukan di server.
 * Tanpa tabel ini, server hanya menerima "foto + koordinat" dan tidak punya
 * catatan apa pun tentang bagaimana klien mengambil kesimpulan bahwa wajahnya
 * cocok. Aplikasi yang di-repackage bisa melompati verifikasi tanpa
 * meninggalkan jejak.
 *
 * Tabel ini TIDAK membuat klaim klien jadi tepercaya -- klien yang di-tamper
 * tetap bisa mengarang skor. Yang berubah: ia harus MENGARANG, bukan sekadar
 * menghilang, dan hasil karangannya tersimpan permanen, dapat diuji
 * kewajarannya, serta dapat dibandingkan antar-karyawan saat investigasi.
 *
 * Dipisah dari tabel attendances karena satu baris absensi memuat dua event
 * (masuk & pulang); menempelkannya berarti menggandakan tujuh kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();

            // Didenormalisasi dari attendances: memungkinkan pencarian
            // "semua bukti janggal milik karyawan X" tanpa join.
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['masuk', 'pulang']);

            // Skor mentah cosine similarity yang dilaporkan klien (0..1).
            // Null bila aplikasi lama tidak mengirim bukti.
            $table->decimal('face_match_score', 6, 5)->nullable();

            // Ambang yang DIPAKAI KLIEN saat memutuskan. Disimpan apa adanya
            // untuk dibandingkan dengan kebijakan server -- ambang klien yang
            // terlalu longgar adalah tanda aplikasi sudah dimodifikasi.
            $table->decimal('client_threshold', 6, 5)->nullable();

            // Ambang server yang berlaku saat absensi ini dinilai. Disimpan
            // agar keputusan lama tetap bisa dibaca setelah kebijakan diubah.
            $table->decimal('server_threshold', 6, 5)->nullable();

            $table->boolean('liveness_passed')->nullable();

            // Urutan tantangan liveness yang dijalani (mis. ["blink","turnLeft"])
            // beserta durasinya. Urutan yang selalu identik antar-absensi
            // adalah tanda kuat rekaman ulang, karena urutannya diacak.
            $table->json('liveness_challenges')->nullable();

            // Identitas instalasi aplikasi (acak, dibuat sekali per instal).
            // Bukan identitas perangkat keras -- cukup untuk melihat satu akun
            // yang tiba-tiba absen dari instalasi berbeda.
            $table->string('device_id', 64)->nullable()->index();

            // Waktu tangkap menurut jam HP vs waktu terima menurut server.
            $table->timestamp('client_captured_at')->nullable();
            $table->timestamp('server_received_at');
            $table->integer('clock_skew_seconds')->nullable();

            // Hasil penilaian server: daftar kejanggalan, mis.
            // ["score_below_policy","clock_skew"]. Kosong = tidak ada temuan.
            $table->json('flags')->nullable();
            $table->boolean('is_flagged')->default(false)->index();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_evidences');
    }
};

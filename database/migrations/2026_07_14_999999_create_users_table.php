<?php
/*
|==================================================================
| FITUR: Migrasi Tabel Admin
| Membuat tabel users (akun admin panel) dan password_reset_tokens.
|==================================================================
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel users = akun admin panel web, bukan karyawan. Skemanya sengaja
 * berbeda dari bawaan Laravel: tiap admin terhubung ke satu employee
 * (employee_id + nip), dan tidak ada kolom name / email_verified_at.
 *
 * File ini pernah ada dan dijalankan (tercatat di tabel migrations), lalu
 * terhapus, sehingga tidak ada lagi migrasi yang menggambarkan kedua tabel
 * ini. Akibatnya `migrate:fresh` menghasilkan database tanpa tabel admin.
 * Isi di bawah direkonstruksi dari struktur tabel yang berjalan di
 * database, agar database bisa dibangun ulang dari nol dengan benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->string('nip');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->string('email');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('nip')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

<?php
/*
|==================================================================
| FITUR: Migrasi Kolom Password
| Menambahkan kolom password (disimpan sebagai hash) pada tabel employees.
|==================================================================
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom password ke tabel employees.
 * -------------------------------------------------------------------------
 * Diperlukan karena skema lama tidak menyimpan kredensial -> login hanya
 * berbasis NIP. Password WAJIB disimpan sebagai hash (bcrypt/argon2), tidak
 * pernah plain text. Gunakan Hash::make() saat set / reset password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('password')->nullable()->after('nama_lengkap');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
};

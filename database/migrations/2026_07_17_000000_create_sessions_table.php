<?php
/*
|==================================================================
| FITUR: Migrasi Tabel Sesi
| Membuat tabel sessions yang dibutuhkan SESSION_DRIVER=database.
|==================================================================
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * .env memakai SESSION_DRIVER=database, tetapi tabel sessions tidak pernah
 * terbuat: migrasi bawaan yang membuatnya (0001_01_01_000000) tidak pernah
 * dijalankan karena tabel users terlanjur dibuat migrasi lain. Akibatnya
 * seluruh panel admin balas HTTP 500 "Base table or view not found:
 * sessions" pada setiap request.
 *
 * Dipisah ke migrasinya sendiri agar tidak lagi bergantung pada migrasi
 * users yang skemanya sudah dikustomisasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};

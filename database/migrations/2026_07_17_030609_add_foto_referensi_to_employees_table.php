<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Path di disk privat (storage/app/private) ke foto referensi
            // wajah karyawan, dipakai backend untuk menyajikan gambar yang
            // dibandingkan EmbeddingFaceMatcher (MobileFaceNet) di klien.
            $table->string('foto_referensi')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('foto_referensi');
        });
    }
};

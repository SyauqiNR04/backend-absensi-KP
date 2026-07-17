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
        Schema::table('attendances', function (Blueprint $table) {
            $table->timestamp('waktu_pulang')->nullable()->after('waktu_absen');
            $table->string('foto_pulang')->nullable()->after('foto_bukti');
            $table->decimal('latitude_pulang', 10, 7)->nullable()->after('longitude');
            $table->decimal('longitude_pulang', 10, 7)->nullable()->after('latitude_pulang');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'waktu_pulang',
                'foto_pulang',
                'latitude_pulang',
                'longitude_pulang',
            ]);
        });
    }
};

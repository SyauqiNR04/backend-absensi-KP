<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            // Menambahkan kolom batas jam masuk dan jam pulang
            $table->time('jam_masuk')->default('08:00:00')->after('radius_meter');
            $table->time('jam_pulang')->default('17:00:00')->after('jam_masuk');
        });
    }

    public function down()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['jam_masuk', 'jam_pulang']);
        });
    }
};
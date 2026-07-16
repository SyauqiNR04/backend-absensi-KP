<?php
/*
|==================================================================
| FITUR: Migrasi Tabel Audit
| Membuat tabel audit_logs append-only untuk logging keamanan.
|==================================================================
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_logs
 * -------------------------------------------------------------------------
 * Jejak audit keamanan yang tak dapat disangkal (OWASP A09: Security Logging
 * & Monitoring Failures). Menyimpan event penting: login gagal, absensi,
 * penolakan integritas perangkat, dan anomali lokasi — untuk investigasi
 * insiden dan deteksi penyalahgunaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');                 // mis. attendance.created, location.anomaly
            $table->string('severity')->default('info'); // info | warning | critical
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable();     // detail terstruktur
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['employee_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

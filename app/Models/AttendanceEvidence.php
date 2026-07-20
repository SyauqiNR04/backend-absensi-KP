<?php
/*
|==================================================================
| FITUR: Bukti Verifikasi Absensi
| Menyimpan klaim verifikasi wajah dari klien beserta hasil penilaian server.
|==================================================================
*/

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceEvidence — append-only, seperti AuditLog. Bukti yang sudah tercatat
 * tidak boleh disunting: begitu bisa diubah, ia berhenti menjadi bukti.
 */
class AttendanceEvidence extends Model
{
    // Eksplisit: Laravel menyingularkan "Evidences" menjadi "evidence",
    // sehingga tanpa baris ini ia mencari tabel 'attendance_evidence'.
    protected $table = 'attendance_evidences';

    public $timestamps = false;

    protected $fillable = [
        'attendance_id', 'employee_id', 'type',
        'face_match_score', 'client_threshold', 'server_threshold',
        'liveness_passed', 'liveness_challenges',
        'device_id', 'client_captured_at', 'server_received_at',
        'clock_skew_seconds', 'flags', 'is_flagged',
    ];

    protected $casts = [
        'face_match_score'    => 'float',
        'client_threshold'    => 'float',
        'server_threshold'    => 'float',
        'liveness_passed'     => 'boolean',
        'liveness_challenges' => 'array',
        'flags'               => 'array',
        'is_flagged'          => 'boolean',
        'client_captured_at'  => 'datetime',
        'server_received_at'  => 'datetime',
        'created_at'          => 'datetime',
    ];

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

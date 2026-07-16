<?php
/*
|==================================================================
| FITUR: Audit Log
| Model pencatatan jejak keamanan bersifat append-only untuk investigasi insiden (OWASP A09).
|==================================================================
*/

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AuditLog — hanya untuk penulisan (append-only). Jangan sediakan update/delete
 * pada level aplikasi agar jejak audit tetap utuh.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'employee_id', 'event', 'severity', 'ip_address', 'user_agent', 'context',
    ];

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    public static function record(string $event, array $data = []): self
    {
        $request = request();

        return static::create([
            'employee_id' => $data['employee_id'] ?? null,
            'event'       => $event,
            'severity'    => $data['severity'] ?? 'info',
            'ip_address'  => $request?->ip(),
            'user_agent'  => substr((string) $request?->userAgent(), 0, 255),
            'context'     => $data['context'] ?? null,
        ]);
    }
}

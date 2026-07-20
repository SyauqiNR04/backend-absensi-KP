<?php
/*
|==================================================================
| FITUR: Uji Deteksi Anomali Lokasi
| Membuktikan impossible-travel terdeteksi dan pergerakan wajar lolos.
|==================================================================
*/

namespace Tests\Unit;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\Security\LocationAnomalyDetector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocationAnomalyDetectorTest extends TestCase
{
    use RefreshDatabase;

    /** Lompatan Jakarta -> Surabaya dalam 1 menit = mustahil. */
    public function test_flags_impossible_travel(): void
    {
        $e = Employee::create(['nip' => 'X', 'nama_lengkap' => 'X', 'jabatan' => 'Staf', 'password' => Hash::make('x')]);
        $t0 = Carbon::parse('2026-07-16 08:00:00');

        Attendance::create([
            'employee_id' => $e->id, 'waktu_absen' => $t0,
            // foto_bukti wajib sejak absensi dijamin selalu punya bukti foto.
            'status' => 'hadir', 'latitude' => -6.2088, 'longitude' => 106.8456, // Jakarta
            'foto_bukti' => 'absensi/uji.jpg',
        ]);

        $detector = new LocationAnomalyDetector();
        $result = $detector->evaluate($e->id, -7.2575, 112.7521, $t0->copy()->addMinute()); // Surabaya

        $this->assertTrue($result['is_anomaly']);
    }

    /** Pergerakan kecil dalam beberapa menit = wajar. */
    public function test_allows_plausible_movement(): void
    {
        $e = Employee::create(['nip' => 'Y', 'nama_lengkap' => 'Y', 'jabatan' => 'Staf', 'password' => Hash::make('x')]);
        $t0 = Carbon::parse('2026-07-16 08:00:00');

        Attendance::create([
            'employee_id' => $e->id, 'waktu_absen' => $t0,
            // foto_bukti wajib sejak absensi dijamin selalu punya bukti foto.
            'status' => 'hadir', 'latitude' => -6.2088, 'longitude' => 106.8456,
            'foto_bukti' => 'absensi/uji.jpg',
        ]);

        $detector = new LocationAnomalyDetector();
        $result = $detector->evaluate($e->id, -6.2090, 106.8460, $t0->copy()->addMinutes(10));

        $this->assertFalse($result['is_anomaly']);
    }
}

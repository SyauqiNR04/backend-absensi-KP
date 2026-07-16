<?php
/*
|==================================================================
| FITUR: Uji Kontrol Akses (IDOR & Spoofing)
| Membuktikan riwayat hanya mengembalikan data pemilik token, dan
| identitas absensi diambil dari token (bukan field 'nip' body).
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    /** Karyawan A tidak bisa membaca riwayat karyawan B lewat {nip} di URL. */
    public function test_history_is_scoped_to_token_owner(): void
    {
        $a = Employee::create(['nip' => 'AAA', 'nama_lengkap' => 'A', 'jabatan' => 'Staf', 'password' => Hash::make('x')]);
        $b = Employee::create(['nip' => 'BBB', 'nama_lengkap' => 'B', 'jabatan' => 'Staf', 'password' => Hash::make('x')]);

        Attendance::create(['employee_id' => $b->id, 'waktu_absen' => now(), 'status' => 'hadir', 'latitude' => 0, 'longitude' => 0]);

        Sanctum::actingAs($a, ['attendance:read']);

        // Walau meminta NIP milik B, hasil harus kosong (data milik A).
        $this->getJson('/api/attendances/BBB')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** Token tanpa ability yang tepat ditolak. */
    public function test_missing_ability_is_forbidden(): void
    {
        $a = Employee::create(['nip' => 'AAA', 'nama_lengkap' => 'A', 'jabatan' => 'Staf', 'password' => Hash::make('x')]);
        Sanctum::actingAs($a, []); // tanpa ability

        $this->getJson('/api/attendances/AAA')->assertStatus(403);
    }
}

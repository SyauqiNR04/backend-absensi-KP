<?php
/*
|==================================================================
| FITUR: Uji Siklus Hidup Karyawan
| Membuktikan penonaktifan mencabut akses tanpa menghapus riwayat absensi,
| serta status akun tidak bocor ke penebak NIP.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class EmployeeLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    private function makeEmployee(string $status = Employee::STATUS_ACTIVE): Employee
    {
        return Employee::create([
            'nip'          => '12345',
            'nama_lengkap' => 'Uji Karyawan',
            'jabatan'      => 'Staf',
            'status'       => $status,
            'password'     => Hash::make(self::PASSWORD),
        ]);
    }

    private function login(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/login', ['nip' => '12345', 'password' => self::PASSWORD]);
    }

    /** Data lama tanpa status eksplisit harus tetap bisa login. */
    public function test_status_defaults_to_active(): void
    {
        Employee::create([
            'nip'          => '12345',
            'nama_lengkap' => 'Uji Karyawan',
            'jabatan'      => 'Staf',
            'password'     => Hash::make(self::PASSWORD),
        ]);

        $this->assertSame(Employee::STATUS_ACTIVE, Employee::first()->status);
        $this->login()->assertOk();
    }

    /** Karyawan nonaktif ditolak walau passwordnya benar. */
    public function test_inactive_employee_cannot_login(): void
    {
        $this->makeEmployee(Employee::STATUS_INACTIVE);
        $this->login()->assertStatus(403);
    }

    /** Pendaftar yang belum disetujui ditolak dengan pesan yang sesuai. */
    public function test_pending_employee_cannot_login(): void
    {
        $this->makeEmployee(Employee::STATUS_PENDING);
        $this->login()
            ->assertStatus(403)
            ->assertJsonPath('message', 'Akun Anda belum disetujui admin.');
    }

    /**
     * INTI: menonaktifkan TIDAK boleh menghapus riwayat absensi.
     * Sebelumnya satu-satunya cara mengeluarkan karyawan adalah menghapus
     * barisnya, dan cascade pada attendances ikut membuang bukti penggajian.
     */
    public function test_deactivate_preserves_attendance_history(): void
    {
        $employee = $this->makeEmployee();

        Attendance::create([
            'employee_id' => $employee->id,
            'waktu_absen' => now(),
            'status'      => 'hadir',
            'latitude'    => -6.2088,
            'longitude'   => 106.8456,
            'foto_bukti'  => 'absensi/uji.jpg',
        ]);

        $employee->deactivate();

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('employees', [
            'id'     => $employee->id,
            'status' => Employee::STATUS_INACTIVE,
        ]);
    }

    /** Menonaktifkan mencabut token, sehingga sesi di HP langsung mati. */
    public function test_deactivate_revokes_active_tokens(): void
    {
        $employee = $this->makeEmployee();
        $token = $employee->createToken('mobile', ['attendance:read'])->plainTextToken;

        $employee->deactivate();

        $this->assertCount(0, $employee->fresh()->tokens);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/attendances/12345')
            ->assertStatus(401);
    }

    /** Karyawan yang diaktifkan kembali bisa login lagi. */
    public function test_reactivated_employee_can_login_again(): void
    {
        $employee = $this->makeEmployee(Employee::STATUS_INACTIVE);
        $this->login()->assertStatus(403);

        $employee->forceFill(['status' => Employee::STATUS_ACTIVE])->save();

        $this->login()->assertOk();
    }

    /**
     * Status akun tidak boleh bocor ke penebak NIP: tanpa password yang benar,
     * karyawan nonaktif dan NIP yang tidak ada harus memberi respons identik.
     */
    public function test_status_is_not_leaked_without_correct_password(): void
    {
        $this->makeEmployee(Employee::STATUS_INACTIVE);

        $inactive = $this->postJson('/api/login', ['nip' => '12345', 'password' => 'salah-sekali']);
        $missing  = $this->postJson('/api/login', ['nip' => '99999', 'password' => 'salah-sekali']);

        $this->assertSame(401, $inactive->status());
        $this->assertSame($inactive->status(), $missing->status());
        $this->assertSame($inactive->json('message'), $missing->json('message'));
    }
}

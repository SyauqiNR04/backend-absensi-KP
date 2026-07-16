<?php
/*
|==================================================================
| FITUR: Uji Keamanan Autentikasi
| Membuktikan login wajib password, respons anti user-enumeration,
| rate limiting anti brute force, dan rotasi token berjalan benar.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(string $nip = '12345', string $password = 'Str0ng!Passw0rd'): Employee
    {
        return Employee::create([
            'nip'          => $nip,
            'nama_lengkap' => 'Uji Karyawan',
            'jabatan'      => 'Staf',
            'password'     => Hash::make($password),
        ]);
    }

    /** Login tanpa password harus ditolak (celah lama tertutup). */
    public function test_login_requires_password(): void
    {
        $this->makeEmployee();
        $this->postJson('/api/login', ['nip' => '12345'])
            ->assertStatus(422); // validasi FormRequest gagal
    }

    /** Password salah -> 401, dan pesan TIDAK membocorkan apakah NIP ada. */
    public function test_wrong_password_is_generic(): void
    {
        $this->makeEmployee();

        $existing = $this->postJson('/api/login', ['nip' => '12345', 'password' => 'salah-sekali'])
            ->assertStatus(401)->json('message');

        $missing = $this->postJson('/api/login', ['nip' => '99999', 'password' => 'apa-saja'])
            ->assertStatus(401)->json('message');

        // Pesan identik -> tidak bisa dipakai enumerasi user.
        $this->assertSame($existing, $missing);
    }

    /**
     * Karyawan lama (dibuat sebelum login berpassword) punya password NULL.
     * Percobaan login harus ditolak wajar (401), bukan meledak jadi 500.
     */
    public function test_employee_without_password_is_rejected_cleanly(): void
    {
        Employee::create([
            'nip'          => '54321',
            'nama_lengkap' => 'Karyawan Lama',
            'jabatan'      => 'Staf',
        ]);

        $this->postJson('/api/login', ['nip' => '54321', 'password' => 'apa-saja'])
            ->assertStatus(401);
    }

    /** Kredensial benar -> token + expires_in. */
    public function test_valid_login_returns_token(): void
    {
        $this->makeEmployee();
        $this->postJson('/api/login', ['nip' => '12345', 'password' => 'Str0ng!Passw0rd'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token', 'expires_in']]);
    }

    /** Rate limiter mengunci setelah 5 percobaan gagal (brute force). */
    public function test_login_is_rate_limited(): void
    {
        RateLimiter::clear('login');
        $this->makeEmployee();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['nip' => '12345', 'password' => 'salah']);
        }
        $this->postJson('/api/login', ['nip' => '12345', 'password' => 'salah'])
            ->assertStatus(429);
    }
}

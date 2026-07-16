<?php
/*
|==================================================================
| FITUR: Uji Pemberian Password oleh Admin
| Membuktikan admin dapat membuat akun karyawan yang benar-benar bisa login,
| serta mereset password karyawan tanpa menyisakan sesi lama.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminEmployeePasswordTest extends TestCase
{
    use RefreshDatabase;

    /** Kuat & acak, supaya tidak tertolak pengecekan kebocoran (HIBP). */
    private const STRONG = 'Kj9#mQ2vLx8@Wz4!';

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
        $this->actingAs(User::factory()->create()); // admin (guard web)
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nip'                   => '12345',
            'nama_lengkap'          => 'Karyawan Baru',
            'jabatan'               => 'Staf',
            'password'              => self::STRONG,
            'password_confirmation' => self::STRONG,
        ], $override);
    }

    /**
     * INTI: karyawan yang dibuat admin harus benar-benar bisa login.
     * Sebelumnya form admin tidak punya field password sama sekali, sehingga
     * setiap akun baru lahir tanpa password dan tidak pernah bisa masuk.
     */
    public function test_admin_creates_employee_that_can_actually_login(): void
    {
        $this->post('/admin/employees', $this->payload())
            ->assertRedirect('/admin/employees');

        $this->postJson('/api/login', ['nip' => '12345', 'password' => self::STRONG])
            ->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    /** Password disimpan sebagai hash, tidak pernah plain text. */
    public function test_password_is_stored_hashed(): void
    {
        $this->post('/admin/employees', $this->payload());

        $stored = Employee::first()->password;

        $this->assertNotSame(self::STRONG, $stored);
        $this->assertTrue(Hash::check(self::STRONG, $stored));
    }

    /** Akun tanpa password tidak boleh dibuat lagi. */
    public function test_password_is_required(): void
    {
        $this->post('/admin/employees', $this->payload([
            'password'              => '',
            'password_confirmation' => '',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('employees', 0);
    }

    /** Kebijakan password kuat ditegakkan (min 12, campuran, simbol). */
    public function test_weak_password_is_rejected(): void
    {
        $this->post('/admin/employees', $this->payload([
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('employees', 0);
    }

    /** Konfirmasi yang tidak cocok ditolak. */
    public function test_password_confirmation_must_match(): void
    {
        $this->post('/admin/employees', $this->payload([
            'password_confirmation' => 'Beda!Sekali9#xy',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('employees', 0);
    }

    /** Edit tanpa mengisi password tidak boleh mengubah password. */
    public function test_editing_without_password_keeps_it_unchanged(): void
    {
        $employee = Employee::create([
            'nip'          => '12345',
            'nama_lengkap' => 'Karyawan',
            'jabatan'      => 'Staf',
            'password'     => Hash::make(self::STRONG),
        ]);

        $this->put('/admin/employees/' . $employee->id, [
            'nip'          => '12345',
            'nama_lengkap' => 'Karyawan Diubah',
            'jabatan'      => 'Manajer',
            'password'     => '',
        ])->assertRedirect('/admin/employees');

        $this->assertTrue(Hash::check(self::STRONG, $employee->fresh()->password));
        $this->assertSame('Karyawan Diubah', $employee->fresh()->nama_lengkap);
    }

    /**
     * Reset password harus mencabut sesi lama. Kalau tidak, sesi yang sudah
     * dikuasai orang lain tetap hidup walau passwordnya sudah diganti.
     */
    public function test_password_reset_revokes_existing_sessions(): void
    {
        $employee = Employee::create([
            'nip'          => '12345',
            'nama_lengkap' => 'Karyawan',
            'jabatan'      => 'Staf',
            'password'     => Hash::make('Lama!Password9#z'),
        ]);
        $employee->createToken('mobile', ['attendance:read']);

        $this->put('/admin/employees/' . $employee->id, [
            'nip'                   => '12345',
            'nama_lengkap'          => 'Karyawan',
            'jabatan'               => 'Staf',
            'password'              => self::STRONG,
            'password_confirmation' => self::STRONG,
        ])->assertRedirect('/admin/employees');

        $this->assertCount(0, $employee->fresh()->tokens);
        $this->postJson('/api/login', ['nip' => '12345', 'password' => self::STRONG])->assertOk();
    }
}

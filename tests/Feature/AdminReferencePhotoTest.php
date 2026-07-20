<?php
/*
|==================================================================
| FITUR: Uji Unggah Foto Referensi Wajah oleh Admin
| Foto ini adalah satu-satunya pembanding yang dipakai aplikasi untuk
| mencocokkan wajah saat absen, sekaligus data biometrik. Uji ini menjaga dua
| hal: fotonya benar-benar sampai ke karyawan yang dituju, dan ia tidak pernah
| bocor ke disk publik atau ke pengunjung yang belum login.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminReferencePhotoTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG = 'Kj9#mQ2vLx8@Wz4!';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->actingAs(User::factory()->create()); // admin (guard web)
    }

    private function foto(int $w = 400, int $h = 400, string $nama = 'wajah.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, $w, $h);
    }

    private function karyawan(array $override = []): Employee
    {
        return Employee::create(array_merge([
            'nip'          => '12345',
            'nama_lengkap' => 'Karyawan Uji',
            'jabatan'      => 'Staf',
            'password'     => Hash::make(self::STRONG),
            'status'       => Employee::STATUS_ACTIVE,
        ], $override));
    }

    /** INTI: foto yang diunggah saat menambah karyawan tersimpan & terhubung. */
    public function test_admin_dapat_mengunggah_foto_saat_membuat_karyawan(): void
    {
        $this->post('/admin/employees', [
            'nip'                   => '12345',
            'nama_lengkap'          => 'Karyawan Baru',
            'jabatan'               => 'Staf',
            'password'              => self::STRONG,
            'password_confirmation' => self::STRONG,
            'foto_referensi'        => $this->foto(),
        ])->assertRedirect('/admin/employees');

        $employee = Employee::where('nip', '12345')->firstOrFail();

        $this->assertNotNull($employee->foto_referensi);
        Storage::disk('local')->assertExists($employee->foto_referensi);
    }

    /** INTI: karyawan lama yang belum punya foto bisa dilengkapi lewat Edit. */
    public function test_admin_dapat_menambahkan_foto_lewat_edit(): void
    {
        $employee = $this->karyawan();
        $this->assertNull($employee->foto_referensi);

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => $this->foto(),
        ])->assertRedirect('/admin/employees');

        Storage::disk('local')->assertExists($employee->fresh()->foto_referensi);
    }

    /**
     * Mengganti foto harus membuang berkas lama. Kalau tidak, foto wajah yang
     * sudah tidak dirujuk siapa pun menumpuk sebagai data biometrik yatim.
     */
    public function test_mengganti_foto_menghapus_berkas_lama(): void
    {
        $employee = $this->karyawan();

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => $this->foto(nama: 'lama.jpg'),
        ]);
        $lama = $employee->fresh()->foto_referensi;

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => $this->foto(nama: 'baru.jpg'),
        ]);
        $baru = $employee->fresh()->foto_referensi;

        $this->assertNotSame($lama, $baru);
        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($baru);
    }

    /**
     * REGRESI: mengedit jabatan tanpa menyertakan berkas tidak boleh diam-diam
     * menghapus foto -- karyawan akan gagal absen tanpa sebab yang terlihat.
     */
    public function test_edit_tanpa_unggah_mempertahankan_foto_lama(): void
    {
        $employee = $this->karyawan();

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => $this->foto(),
        ]);
        $foto = $employee->fresh()->foto_referensi;

        $this->put('/admin/employees/' . $employee->id, [
            'nip'          => $employee->nip,
            'nama_lengkap' => $employee->nama_lengkap,
            'jabatan'      => 'Manajer',
        ])->assertRedirect('/admin/employees');

        $this->assertSame($foto, $employee->fresh()->foto_referensi);
        $this->assertSame('Manajer', $employee->fresh()->jabatan);
        Storage::disk('local')->assertExists($foto);
    }

    /** Berkas non-gambar ditolak: PDF/skrip tidak akan bisa jadi acuan wajah. */
    public function test_berkas_bukan_gambar_ditolak(): void
    {
        $employee = $this->karyawan();

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => UploadedFile::fake()->create('virus.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('foto_referensi');

        $this->assertNull($employee->fresh()->foto_referensi);
    }

    /**
     * Gambar terlalu kecil ditolak: wajahnya tidak cukup detail untuk
     * menghasilkan embedding yang andal, dan kegagalannya baru muncul di
     * lapangan saat karyawan tidak dikenali.
     */
    public function test_gambar_beresolusi_terlalu_kecil_ditolak(): void
    {
        $employee = $this->karyawan();

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => $this->foto(100, 100),
        ])->assertSessionHasErrors('foto_referensi');

        $this->assertNull($employee->fresh()->foto_referensi);
    }

    /** Admin bisa mencabut foto yang salah unggah tanpa menghapus karyawannya. */
    public function test_admin_dapat_menghapus_foto_referensi(): void
    {
        $employee = $this->karyawan();

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => $this->foto(),
        ]);
        $foto = $employee->fresh()->foto_referensi;

        $this->delete('/admin/employees/' . $employee->id . '/photo')
            ->assertRedirect('/admin/employees/' . $employee->id . '/edit');

        $this->assertNull($employee->fresh()->foto_referensi);
        Storage::disk('local')->assertMissing($foto);
        $this->assertNotNull($employee->fresh()); // karyawannya tetap ada
    }

    /**
     * KEAMANAN: foto biometrik tidak boleh mendarat di disk publik, yang isinya
     * dapat diunduh siapa saja tanpa login lewat /storage.
     */
    public function test_foto_tidak_disimpan_di_disk_publik(): void
    {
        Storage::fake('public');
        $employee = $this->karyawan();

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => $this->foto(),
        ]);

        $this->assertEmpty(Storage::disk('public')->allFiles());
        Storage::disk('local')->assertExists($employee->fresh()->foto_referensi);
    }

    /** KEAMANAN: penyajian foto wajib di balik login admin. */
    public function test_tamu_tidak_dapat_melihat_foto_referensi(): void
    {
        $employee = $this->karyawan();

        $this->put('/admin/employees/' . $employee->id, [
            'nip'            => $employee->nip,
            'nama_lengkap'   => $employee->nama_lengkap,
            'jabatan'        => $employee->jabatan,
            'foto_referensi' => $this->foto(),
        ]);

        $this->app['auth']->guard('web')->logout();
        session()->flush();

        $this->get('/admin/employees/' . $employee->id . '/photo')
            ->assertRedirect('/admin/login');
    }
}

<?php
/*
|==================================================================
| FITUR: Uji Perkakas Pemindahan Foto ke Disk Privat
| Perintah ini menyentuh berkas asli milik pengguna, jadi perilakunya dikunci
| di sini sebelum dijalankan: uji coba tidak boleh mengubah apa pun, berkas
| bentrok tidak boleh ditimpa, dan riwayat harus tetap terbaca setelahnya.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MoveAttendancePhotosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function absensiDengan(string $path): Attendance
    {
        $employee = Employee::create([
            'nip'          => '12345',
            'nama_lengkap' => 'Budi',
            'jabatan'      => 'Staf',
            'password'     => Hash::make('Kj9#mQ2vLx8@Wz4!'),
            'status'       => Employee::STATUS_ACTIVE,
        ]);

        return Attendance::create([
            'employee_id' => $employee->id,
            'waktu_absen' => Carbon::now('Asia/Jakarta'),
            'status'      => 'hadir',
            'latitude'    => -6.2,
            'longitude'   => 106.8,
            'foto_bukti'  => $path,
        ]);
    }

    /** Uji coba tidak boleh menyentuh berkas sama sekali. */
    public function test_dry_run_tidak_mengubah_apa_pun(): void
    {
        Storage::disk('public')->put('absensi/lama.jpg', 'isi-foto');

        $this->artisan('absensi:pindahkan-foto-privat', ['--dry-run' => true])
            ->assertExitCode(0);

        Storage::disk('public')->assertExists('absensi/lama.jpg');
        Storage::disk('local')->assertMissing('absensi/lama.jpg');
    }

    /**
     * INTI: berkas pindah ke path relatif YANG SAMA, dan basis data tidak
     * disentuh -- itulah yang membuat riwayat tetap terbaca setelahnya.
     */
    public function test_berkas_pindah_tanpa_mengubah_basis_data(): void
    {
        $absen = $this->absensiDengan('absensi/lama.jpg');
        Storage::disk('public')->put('absensi/lama.jpg', 'isi-foto');

        $this->artisan('absensi:pindahkan-foto-privat')->assertExitCode(0);

        Storage::disk('local')->assertExists('absensi/lama.jpg');
        Storage::disk('public')->assertMissing('absensi/lama.jpg');

        $this->assertSame('isi-foto', Storage::disk('local')->get('absensi/lama.jpg'));
        $this->assertSame('absensi/lama.jpg', $absen->fresh()->foto_bukti);
    }

    /** Berkas yatim ikut dipindah: tidak dirujuk bukan berarti boleh terbuka. */
    public function test_berkas_yatim_ikut_dipindah(): void
    {
        Storage::disk('public')->put('attendances/yatim.jpg', 'wajah');

        $this->artisan('absensi:pindahkan-foto-privat')->assertExitCode(0);

        Storage::disk('local')->assertExists('attendances/yatim.jpg');
        Storage::disk('public')->assertMissing('attendances/yatim.jpg');
    }

    /**
     * KESELAMATAN DATA: nama yang sudah ada di tujuan tidak boleh ditimpa --
     * itu akan menghapus foto absensi lain secara diam-diam. Sumbernya juga
     * dipertahankan supaya tidak ada yang hilang sebelum diperiksa manusia.
     */
    public function test_berkas_bentrok_tidak_ditimpa(): void
    {
        Storage::disk('local')->put('absensi/sama.jpg', 'foto-privat-asli');
        Storage::disk('public')->put('absensi/sama.jpg', 'foto-publik-lain');

        $this->artisan('absensi:pindahkan-foto-privat')->assertExitCode(0);

        $this->assertSame('foto-privat-asli', Storage::disk('local')->get('absensi/sama.jpg'));
        Storage::disk('public')->assertExists('absensi/sama.jpg');
    }

    /** Folder di luar foto absensi tidak ikut tersapu. */
    public function test_folder_lain_tidak_disentuh(): void
    {
        Storage::disk('public')->put('dokumen/panduan.pdf', 'bukan-foto');

        $this->artisan('absensi:pindahkan-foto-privat')->assertExitCode(0);

        Storage::disk('public')->assertExists('dokumen/panduan.pdf');
        Storage::disk('local')->assertMissing('dokumen/panduan.pdf');
    }

    /** Tidak ada berkas tersisa -> jalan tanpa error, aman diulang. */
    public function test_aman_dijalankan_ulang(): void
    {
        Storage::disk('public')->put('absensi/lama.jpg', 'isi');

        $this->artisan('absensi:pindahkan-foto-privat')->assertExitCode(0);
        $this->artisan('absensi:pindahkan-foto-privat')->assertExitCode(0);

        Storage::disk('local')->assertExists('absensi/lama.jpg');
    }
}

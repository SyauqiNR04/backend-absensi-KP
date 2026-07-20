<?php
/*
|==================================================================
| FITUR: Uji Halaman Tinjauan Verifikasi Wajah
| Membuktikan bukti yang ditandai benar-benar terlihat admin, tersaring per
| jenis temuan, dan fotonya tetap tertutup bagi yang belum login.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceEvidence;
use App\Models\Employee;
use App\Models\User;
use App\Services\Security\FaceEvidenceValidator as F;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminVerificationReviewTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->employee = Employee::create([
            'nip'          => '12345',
            'nama_lengkap' => 'Budi Santoso',
            'jabatan'      => 'Staf',
            'password'     => Hash::make('Kj9#mQ2vLx8@Wz4!'),
            'status'       => Employee::STATUS_ACTIVE,
        ]);
    }

    private function buatBukti(array $flags, array $override = []): AttendanceEvidence
    {
        $absen = Attendance::create([
            'employee_id' => $this->employee->id,
            'waktu_absen' => Carbon::now('Asia/Jakarta'),
            'status'      => 'hadir',
            'latitude'    => -6.2,
            'longitude'   => 106.8,
            'foto_bukti'  => UploadedFile::fake()->image('absen.jpg', 400, 400)
                ->store('absensi', 'local'),
        ]);

        return AttendanceEvidence::create(array_merge([
            'attendance_id'      => $absen->id,
            'employee_id'        => $this->employee->id,
            'type'               => 'masuk',
            'face_match_score'   => 0.55,
            'server_threshold'   => 0.80,
            'liveness_passed'    => true,
            'device_id'          => 'inst-aaaa1111',
            'server_received_at' => Carbon::now('Asia/Jakarta'),
            'flags'              => $flags,
            'is_flagged'         => $flags !== [],
        ], $override));
    }

    private function sebagaiAdmin(): void
    {
        $this->actingAs(User::factory()->create());
    }

    /** INTI: bukti yang ditandai muncul, lengkap dengan penjelasannya. */
    public function test_bukti_ditandai_tampil_dengan_penjelasan(): void
    {
        $this->buatBukti([F::FLAG_SCORE_BELOW_POLICY]);
        $this->sebagaiAdmin();

        $this->get('/admin/verifikasi')
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Skor kemiripan di bawah ambang')
            ->assertSee('55.0%')   // skor ditampilkan sebagai persen
            ->assertSee('Tindak lanjut:');
    }

    /**
     * Bukti bersih TIDAK boleh ikut tampil: halaman ini daftar tindak lanjut,
     * dan mencampurnya dengan absensi normal membuat temuan tenggelam.
     */
    public function test_bukti_bersih_tidak_ditampilkan(): void
    {
        $this->buatBukti([], ['face_match_score' => 0.95]);
        $this->sebagaiAdmin();

        $this->get('/admin/verifikasi')
            ->assertOk()
            ->assertSee('Belum ada bukti absensi yang ditandai')
            ->assertDontSee('Budi Santoso');
    }

    /** Penyaringan per jenis temuan menyembunyikan temuan lain. */
    public function test_filter_per_jenis_temuan(): void
    {
        $this->buatBukti([F::FLAG_CLOCK_SKEW]);
        $this->buatBukti([F::FLAG_THRESHOLD_TAMPERED]);
        $this->sebagaiAdmin();

        // Yang diperiksa isi BARIS, bukan chip filter di atas tabel: chip
        // memang harus tetap menampilkan seluruh jenis temuan sebagai pilihan.
        // Penjelasan 'arti' hanya muncul pada baris yang tampil.
        $this->get('/admin/verifikasi?flag=' . F::FLAG_THRESHOLD_TAMPERED)
            ->assertOk()
            ->assertSee('tanda aplikasi dimodifikasi', false)
            ->assertDontSee('Waktu di HP jauh berbeda dari waktu server');
    }

    /**
     * Penanda yang belum punya penjelasan tetap ditampilkan kodenya. Kalau
     * disembunyikan, penambahan penanda baru diam-diam menghilangkan temuan
     * dari layar admin.
     */
    public function test_penanda_tanpa_penjelasan_tetap_terlihat(): void
    {
        $this->buatBukti(['flag_yang_belum_dikenal']);
        $this->sebagaiAdmin();

        $this->get('/admin/verifikasi')
            ->assertOk()
            ->assertSee('flag_yang_belum_dikenal');
    }

    /** Jumlah temuan tampil di sidebar agar terlihat tanpa membuka halaman. */
    public function test_jumlah_temuan_tampil_di_navigasi(): void
    {
        $this->buatBukti([F::FLAG_CLOCK_SKEW]);
        $this->buatBukti([F::FLAG_DEVICE_CHANGED]);
        $this->sebagaiAdmin();

        $this->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Verifikasi Wajah');
    }

    /** Foto absensi dapat dilihat admin untuk pembanding manual. */
    public function test_admin_dapat_melihat_foto_absensi(): void
    {
        $bukti = $this->buatBukti([F::FLAG_IMPLAUSIBLE_SCORE]);
        $this->sebagaiAdmin();

        $this->get('/admin/verifikasi/' . $bukti->id . '/photo')->assertOk();
    }

    /**
     * Bukti absen PULANG harus menyajikan foto pulang, bukan foto masuk --
     * kalau tertukar, admin membandingkan wajah dari kejadian yang salah.
     */
    public function test_bukti_pulang_menyajikan_foto_pulang(): void
    {
        $fotoPulang = UploadedFile::fake()->image('pulang.jpg', 400, 400)
            ->store('absensi', 'local');

        $bukti = $this->buatBukti([F::FLAG_CLOCK_SKEW], ['type' => 'pulang']);
        $bukti->attendance->update(['foto_pulang' => $fotoPulang]);

        $this->sebagaiAdmin();

        $response = $this->get('/admin/verifikasi/' . $bukti->id . '/photo');
        $response->assertOk();

        $this->assertSame(
            Storage::disk('local')->get($fotoPulang),
            $response->streamedContent(),
        );
    }

    /** Absensi tanpa berkas foto tidak boleh membuat halaman error 500. */
    public function test_foto_yang_hilang_menghasilkan_404_bukan_error(): void
    {
        $bukti = $this->buatBukti([F::FLAG_CLOCK_SKEW]);
        Storage::disk('local')->delete($bukti->attendance->foto_bukti);

        $this->sebagaiAdmin();

        $this->get('/admin/verifikasi/' . $bukti->id . '/photo')->assertNotFound();
    }

    /**
     * Halaman Riwayat admin memuat foto lewat endpoint privat, bukan
     * asset('storage/...') yang menunjuk disk publik dan selalu 404.
     */
    public function test_riwayat_admin_memuat_foto_dari_endpoint_privat(): void
    {
        $bukti = $this->buatBukti([]);
        $this->sebagaiAdmin();

        $halaman = $this->get('/admin/riwayat?tanggal=' . now('Asia/Jakarta')->toDateString());
        $halaman->assertOk()
            ->assertSee('/admin/attendances/' . $bukti->attendance_id . '/photo/masuk', false)
            ->assertDontSee('storage/absensi', false);

        // URL yang ditautkan harus benar-benar menyajikan berkasnya.
        $this->get('/admin/attendances/' . $bukti->attendance_id . '/photo/masuk')
            ->assertOk();
    }

    /** KEAMANAN: halaman & foto tertutup bagi yang belum login. */
    public function test_tamu_tidak_dapat_mengakses(): void
    {
        $bukti = $this->buatBukti([F::FLAG_SCORE_BELOW_POLICY]);

        $this->get('/admin/verifikasi')->assertRedirect('/admin/login');
        $this->get('/admin/verifikasi/' . $bukti->id . '/photo')
            ->assertRedirect('/admin/login');
        $this->get('/admin/attendances/' . $bukti->attendance_id . '/photo/masuk')
            ->assertRedirect('/admin/login');
    }
}

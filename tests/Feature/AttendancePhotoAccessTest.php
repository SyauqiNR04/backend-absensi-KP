<?php
/*
|==================================================================
| FITUR: Uji Akses Foto Absensi dari Aplikasi
| Foto absensi adalah data biometrik di disk privat. Uji ini menjaga agar
| karyawan hanya bisa melihat fotonya SENDIRI, dan agar layar riwayat menerima
| URL yang benar-benar bisa dimuat.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancePhotoAccessTest extends TestCase
{
    use RefreshDatabase;

    private Employee $budi;
    private Employee $siti;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->budi = $this->karyawan('11111', 'Budi');
        $this->siti = $this->karyawan('22222', 'Siti');
    }

    private function karyawan(string $nip, string $nama): Employee
    {
        return Employee::create([
            'nip'          => $nip,
            'nama_lengkap' => $nama,
            'jabatan'      => 'Staf',
            'password'     => Hash::make('Kj9#mQ2vLx8@Wz4!'),
            'status'       => Employee::STATUS_ACTIVE,
        ]);
    }

    private function absensi(Employee $milik, bool $denganPulang = false): Attendance
    {
        return Attendance::create([
            'employee_id'  => $milik->id,
            'waktu_absen'  => Carbon::now('Asia/Jakarta'),
            'status'       => 'hadir',
            'latitude'     => -6.2,
            'longitude'    => 106.8,
            'foto_bukti'   => UploadedFile::fake()->image('masuk.jpg', 400, 400)
                ->store('absensi', 'local'),
            'waktu_pulang' => $denganPulang ? Carbon::now('Asia/Jakarta') : null,
            // Dimensi sengaja dibedakan dari foto masuk: UploadedFile::fake()
            // menghasilkan byte identik untuk dimensi yang sama, sehingga uji
            // "foto pulang tidak tertukar" tidak akan membuktikan apa pun.
            'foto_pulang'  => $denganPulang
                ? UploadedFile::fake()->image('pulang.jpg', 320, 240)->store('absensi', 'local')
                : null,
        ]);
    }

    /** INTI: karyawan bisa memuat foto absensinya sendiri. */
    public function test_karyawan_dapat_melihat_foto_absensinya_sendiri(): void
    {
        $absen = $this->absensi($this->budi);
        Sanctum::actingAs($this->budi, ['attendance:read']);

        $this->get("/api/attendances/{$absen->id}/photo/masuk")->assertOk();
    }

    /**
     * INTI KEAMANAN (IDOR): id absensi berurutan dan mudah ditebak. Karyawan
     * lain yang sudah login tidak boleh bisa mengunduh foto wajah rekannya
     * hanya dengan mengganti angka di URL.
     */
    public function test_karyawan_tidak_dapat_melihat_foto_milik_orang_lain(): void
    {
        $absenBudi = $this->absensi($this->budi);
        Sanctum::actingAs($this->siti, ['attendance:read']);

        // 404, bukan 403: 403 justru mengonfirmasi bahwa absensi itu ada.
        $this->get("/api/attendances/{$absenBudi->id}/photo/masuk")
            ->assertNotFound();
    }

    /** Foto pulang disajikan dari kolomnya sendiri, tidak tertukar. */
    public function test_foto_pulang_berbeda_dari_foto_masuk(): void
    {
        $absen = $this->absensi($this->budi, denganPulang: true);
        Sanctum::actingAs($this->budi, ['attendance:read']);

        $masuk = $this->get("/api/attendances/{$absen->id}/photo/masuk");
        $pulang = $this->get("/api/attendances/{$absen->id}/photo/pulang");

        $masuk->assertOk();
        $pulang->assertOk();

        $this->assertSame(
            Storage::disk('local')->get($absen->foto_pulang),
            $pulang->streamedContent(),
        );
        $this->assertNotSame(
            $masuk->streamedContent(),
            $pulang->streamedContent(),
        );
    }

    /** Jenis foto di luar masuk/pulang ditolak, tidak dipakai menebak berkas. */
    public function test_jenis_foto_tidak_dikenal_ditolak(): void
    {
        $absen = $this->absensi($this->budi);
        Sanctum::actingAs($this->budi, ['attendance:read']);

        $this->get("/api/attendances/{$absen->id}/photo/sembarang")
            ->assertNotFound();
    }

    /** Belum absen pulang -> 404 yang bersih, bukan error 500. */
    public function test_foto_pulang_yang_belum_ada_menghasilkan_404(): void
    {
        $absen = $this->absensi($this->budi);
        Sanctum::actingAs($this->budi, ['attendance:read']);

        $this->get("/api/attendances/{$absen->id}/photo/pulang")
            ->assertNotFound();
    }

    /** KEAMANAN: tanpa token, foto tidak bisa diakses sama sekali. */
    public function test_tanpa_token_ditolak(): void
    {
        $absen = $this->absensi($this->budi);

        $this->getJson("/api/attendances/{$absen->id}/photo/masuk")
            ->assertUnauthorized();
    }

    /**
     * INTI KONTRAK: riwayat mengirim URL siap pakai. Tanpa ini layar riwayat
     * kembali menebak URL /storage yang sudah tidak berlaku.
     */
    public function test_riwayat_mengirim_url_foto_yang_dapat_dimuat(): void
    {
        $absen = $this->absensi($this->budi, denganPulang: true);
        Sanctum::actingAs($this->budi, ['attendance:read']);

        $riwayat = $this->getJson("/api/history/{$this->budi->nip}")
            ->assertOk()
            ->json('data.0');

        $this->assertStringContainsString(
            "/api/attendances/{$absen->id}/photo/masuk",
            $riwayat['foto_masuk_url'],
        );
        $this->assertStringContainsString(
            "/api/attendances/{$absen->id}/photo/pulang",
            $riwayat['foto_pulang_url'],
        );

        // URL yang dikirim harus benar-benar bisa dimuat, bukan sekadar ada.
        $this->get($riwayat['foto_masuk_url'])->assertOk();
    }

    /** Belum absen pulang -> url pulang null, supaya UI tidak memuat gambar rusak. */
    public function test_url_foto_null_saat_fotonya_belum_ada(): void
    {
        $this->absensi($this->budi);
        Sanctum::actingAs($this->budi, ['attendance:read']);

        $riwayat = $this->getJson("/api/history/{$this->budi->nip}")->json('data.0');

        $this->assertNotNull($riwayat['foto_masuk_url']);
        $this->assertNull($riwayat['foto_pulang_url']);
    }

    /**
     * Path penyimpanan internal tidak ikut terkirim: klien tidak
     * membutuhkannya, dan ia membocorkan struktur direktori server.
     */
    public function test_path_penyimpanan_tidak_bocor_ke_klien(): void
    {
        $this->absensi($this->budi, denganPulang: true);
        Sanctum::actingAs($this->budi, ['attendance:read']);

        $riwayat = $this->getJson("/api/history/{$this->budi->nip}")->json('data.0');

        $this->assertArrayNotHasKey('foto_bukti', $riwayat);
        $this->assertArrayNotHasKey('foto_pulang', $riwayat);
    }
}

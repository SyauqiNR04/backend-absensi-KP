<?php
/*
|==================================================================
| FITUR: Uji Bukti Verifikasi Wajah pada Absensi
| Membuktikan klaim verifikasi dari klien tercatat, diuji terhadap ambang milik
| SERVER, dan kejanggalannya tertandai -- termasuk saat klien mencoba
| melonggarkan ambangnya sendiri.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceEvidence;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\Security\FaceEvidenceValidator as F;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Kantor diletakkan tepat di titik absensi supaya geo-fence tidak
        // ikut menggagalkan uji yang sedang menyorot bukti wajah.
        // forceCreate: model Setting sengaja tidak mengizinkan mass assignment.
        Setting::forceCreate([
            'nama_lokasi'  => 'Kantor Pusat',
            'latitude'     => -6.200000,
            'longitude'    => 106.816666,
            'radius_meter' => 500,
            'jam_masuk'    => '08:00:00',
            'jam_pulang'   => '17:00:00',
        ]);

        $this->employee = Employee::create([
            'nip'          => '12345',
            'nama_lengkap' => 'Karyawan Uji',
            'jabatan'      => 'Staf',
            'password'     => Hash::make('Kj9#mQ2vLx8@Wz4!'),
            'status'       => Employee::STATUS_ACTIVE,
        ]);

        // Ability eksplisit: rute /attendances dijaga middleware
        // 'ability:attendance:submit'.
        Sanctum::actingAs($this->employee, ['attendance:submit']);
    }

    /**
     * Nilai null pada override berarti "field tidak dikirim sama sekali" --
     * itulah yang dilakukan aplikasi versi lama, bukan mengirim null.
     */
    private function payload(array $override = []): array
    {
        return array_filter(array_merge([
            'latitude'             => -6.200000,
            'longitude'            => 106.816666,
            'foto'                 => UploadedFile::fake()->image('absen.jpg', 400, 400),
            'face_match_score'     => 0.91,
            'face_match_threshold' => 0.80,
            'liveness_passed'      => true,
            'liveness_challenges'  => ['blink', 'turnLeft', 'smile'],
            'device_id'            => 'inst-aaaa1111',
            'client_captured_at'   => Carbon::now('Asia/Jakarta')->toIso8601String(),
        ], $override), fn ($v) => $v !== null);
        // Sengaja bukan array_filter default: nilai falsy yang sah
        // (liveness_passed = false) tidak boleh ikut terbuang.
    }

    private function absen(array $override = [])
    {
        return $this->postJson('/api/attendances', $this->payload($override));
    }

    /** INTI: absensi wajar tersimpan lengkap dengan buktinya, tanpa temuan. */
    public function test_bukti_verifikasi_tersimpan_pada_absensi_normal(): void
    {
        $this->absen()->assertStatus(201);

        $bukti = AttendanceEvidence::firstOrFail();

        $this->assertSame('masuk', $bukti->type);
        $this->assertEqualsWithDelta(0.91, $bukti->face_match_score, 0.0001);
        $this->assertTrue($bukti->liveness_passed);
        $this->assertSame(['blink', 'turnLeft', 'smile'], $bukti->liveness_challenges);
        $this->assertSame('inst-aaaa1111', $bukti->device_id);
        $this->assertFalse($bukti->is_flagged);
        $this->assertNull($bukti->flags);
        $this->assertSame($this->employee->id, $bukti->employee_id);
    }

    /** Absen pulang mencatat buktinya sendiri, terpisah dari absen masuk. */
    public function test_absen_pulang_mencatat_bukti_terpisah(): void
    {
        $this->absen()->assertStatus(201);
        $this->absen([
            'liveness_challenges' => ['smile', 'blink'], // urutan diacak ulang
        ])->assertStatus(200);

        $this->assertSame(1, Attendance::count());
        $this->assertSame(2, AttendanceEvidence::count());
        $this->assertSame(
            ['masuk', 'pulang'],
            AttendanceEvidence::orderBy('id')->pluck('type')->all(),
        );
    }

    /**
     * INTI KEAMANAN: ambang yang menentukan adalah milik server.
     * Klien mengaku ambangnya 0.10 dan skornya "lolos" menurut dia sendiri --
     * server tetap menolak karena 0.42 di bawah kebijakannya.
     */
    public function test_skor_di_bawah_ambang_server_ditolak_meski_klien_mengaku_lolos(): void
    {
        $this->absen([
            'face_match_score'     => 0.42,
            'face_match_threshold' => 0.10,
        ])->assertStatus(422)->assertJsonPath('code', 'FACE_EVIDENCE_REJECTED');

        $this->assertSame(0, Attendance::count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'attendance.evidence_rejected']);
    }

    /** Ambang klien yang dilonggarkan = aplikasi dimodifikasi, walau skor tinggi. */
    public function test_ambang_klien_yang_dilonggarkan_ditolak(): void
    {
        $this->absen([
            'face_match_score'     => 0.95,
            'face_match_threshold' => 0.20,
        ])->assertStatus(422);

        $this->assertSame(0, Attendance::count());
    }

    /** Klien yang mengaku liveness gagal tapi tetap mengirim absensi ditolak. */
    public function test_liveness_gagal_ditolak(): void
    {
        $this->absen(['liveness_passed' => false])->assertStatus(422);

        $this->assertSame(0, Attendance::count());
    }

    /**
     * Masa peralihan: aplikasi lama tanpa bukti masih boleh absen, TETAPI
     * absensinya tertandai supaya terlihat di audit -- bukan lolos diam-diam.
     */
    public function test_tanpa_bukti_diterima_namun_ditandai_saat_belum_diwajibkan(): void
    {
        config(['attendance.evidence_required' => false]);

        $this->absen([
            'face_match_score'     => null,
            'face_match_threshold' => null,
            'liveness_passed'      => null,
            'liveness_challenges'  => null,
            'device_id'            => null,
            'client_captured_at'   => null,
        ])->assertStatus(201);

        $bukti = AttendanceEvidence::firstOrFail();

        $this->assertTrue($bukti->is_flagged);
        $this->assertContains(F::FLAG_MISSING, $bukti->flags);
        $this->assertContains(F::FLAG_LIVENESS_MISSING, $bukti->flags);
        $this->assertDatabaseHas('audit_logs', ['event' => 'attendance.evidence_flagged']);
    }

    /**
     * Setelah kebijakan diwajibkan, payload yang sama ditolak. Inilah saklar
     * yang membuat skema ini berarti; selama false ia hanya mencatat.
     */
    public function test_tanpa_bukti_ditolak_setelah_diwajibkan(): void
    {
        config(['attendance.evidence_required' => true]);

        $this->absen([
            'face_match_score'     => null,
            'face_match_threshold' => null,
            'liveness_passed'      => null,
            'liveness_challenges'  => null,
        ])->assertStatus(422);

        $this->assertSame(0, Attendance::count());
    }

    /** Skor 1.0 = foto referensi dibandingkan dirinya sendiri, atau dikarang. */
    public function test_skor_sempurna_ditandai_sebagai_tidak_wajar(): void
    {
        $this->absen(['face_match_score' => 1.0])->assertStatus(201);

        $bukti = AttendanceEvidence::firstOrFail();
        $this->assertTrue($bukti->is_flagged);
        $this->assertContains(F::FLAG_IMPLAUSIBLE_SCORE, $bukti->flags);
    }

    /** Jam HP yang digeser jauh ditandai, tapi tidak membatalkan absensi. */
    public function test_selisih_jam_besar_ditandai_tanpa_menolak(): void
    {
        $this->absen([
            'client_captured_at' => Carbon::now('Asia/Jakarta')->subHours(3)->toIso8601String(),
        ])->assertStatus(201);

        $bukti = AttendanceEvidence::firstOrFail();
        $this->assertTrue($bukti->is_flagged);
        $this->assertContains(F::FLAG_CLOCK_SKEW, $bukti->flags);
        $this->assertGreaterThan(3000, $bukti->clock_skew_seconds);
        $this->assertSame(1, Attendance::count()); // tetap tercatat
    }

    /**
     * Urutan tantangan liveness diacak tiap sesi. Terulang persis berarti
     * rekaman lama diputar ulang atau urutannya nilai tetap hasil tamper.
     */
    public function test_urutan_liveness_yang_terulang_persis_ditandai(): void
    {
        $urutan = ['blink', 'turnRight'];

        $this->absen(['liveness_challenges' => $urutan])->assertStatus(201);
        $this->absen(['liveness_challenges' => $urutan])->assertStatus(200);

        $kedua = AttendanceEvidence::orderByDesc('id')->first();
        $this->assertTrue($kedua->is_flagged);
        $this->assertContains(F::FLAG_REPEATED_CHALLENGE, $kedua->flags);
    }

    /** Ganti perangkat ditandai untuk ditinjau -- punya sebab sah, jadi tidak ditolak. */
    public function test_ganti_perangkat_ditandai_tanpa_menolak(): void
    {
        $this->absen()->assertStatus(201);
        $this->absen([
            'device_id'           => 'inst-bbbb2222',
            'liveness_challenges' => ['smile'],
        ])->assertStatus(200);

        $kedua = AttendanceEvidence::orderByDesc('id')->first();
        $this->assertTrue($kedua->is_flagged);
        $this->assertContains(F::FLAG_DEVICE_CHANGED, $kedua->flags);
    }

    /** Skor di luar rentang 0..1 ditolak validasi bentuk, tidak sampai ke basis data. */
    public function test_skor_di_luar_rentang_ditolak_validasi(): void
    {
        $this->absen(['face_match_score' => 7.5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('face_match_score');

        $this->assertSame(0, AttendanceEvidence::count());
    }

    /**
     * REGRESI: absensi yang ditolak tidak boleh menyisakan foto di disk.
     * Berkas yatim menumpuk sebagai data biometrik tanpa pemilik.
     */
    public function test_absensi_yang_ditolak_tidak_menyimpan_foto(): void
    {
        $this->absen(['face_match_score' => 0.30])->assertStatus(422);

        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    /** REGRESI: submit ketiga tetap ditolak dan juga tidak menyisakan foto. */
    public function test_submit_ketiga_ditolak_tanpa_menyimpan_foto(): void
    {
        $this->absen()->assertStatus(201);
        $this->absen(['liveness_challenges' => ['smile']])->assertStatus(200);

        $jumlahFotoSah = count(Storage::disk('local')->allFiles());

        $this->absen(['liveness_challenges' => ['blink']])->assertStatus(409);

        $this->assertCount($jumlahFotoSah, Storage::disk('local')->allFiles());
    }
}

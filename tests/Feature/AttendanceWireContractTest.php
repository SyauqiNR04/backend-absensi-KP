<?php
/*
|==================================================================
| FITUR: Uji Kontrak Kawat Absensi (Flutter -> Laravel)
| Meniru PERSIS payload multipart yang dikirim aplikasi: seluruh field berupa
| string, karena multipart/form-data tidak mengenal tipe selain teks.
|
| Uji lain memakai postJson dengan boolean & angka asli, sehingga tidak pernah
| menyentuh perbedaan ini -- padahal justru di sinilah klien dan server bisa
| diam-diam tidak sinkron.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceEvidence;
use App\Models\Employee;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceWireContractTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

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

        Sanctum::actingAs($this->employee, ['attendance:submit']);
    }

    /**
     * Persis seperti AttendanceApiService merakit request.fields: setiap nilai
     * sudah melewati .toString() milik Dart. Perhatikan "true"/"false" --
     * itulah keluaran bool.toString() di Dart, bukan "1"/"0".
     */
    private function payloadKawat(array $override = []): array
    {
        return array_merge([
            'latitude'             => '-6.2',
            'longitude'            => '106.816666',
            'is_rooted'            => 'false',
            'is_emulator'          => 'false',
            'is_mock_location'     => 'false',
            'face_match_score'     => '0.9123',
            'face_match_threshold' => '0.8',
            'liveness_passed'      => 'true',
            'liveness_challenges'  => ['blink', 'turnLeft', 'smile'],
            'device_id'            => 'aBcD1234efGh5678',
            'client_captured_at'   => Carbon::now('UTC')->toIso8601String(),
            'foto'                 => UploadedFile::fake()->image('absen.jpg', 400, 400),
        ], $override);
    }

    /**
     * INTI: payload apa adanya dari aplikasi harus diterima. Bila uji ini
     * gagal, absensi di HP gagal total meski logika servernya benar.
     */
    public function test_payload_asli_dari_aplikasi_diterima(): void
    {
        $this->post('/api/attendances', $this->payloadKawat())
            ->assertStatus(201);

        $bukti = AttendanceEvidence::firstOrFail();

        // Nilai string harus mendarat sebagai tipe yang benar di basis data.
        $this->assertEqualsWithDelta(0.9123, $bukti->face_match_score, 0.0001);
        $this->assertTrue($bukti->liveness_passed);
        $this->assertFalse($bukti->is_flagged, 'payload sah tidak boleh ditandai');
        $this->assertSame(['blink', 'turnLeft', 'smile'], $bukti->liveness_challenges);
        $this->assertSame('aBcD1234efGh5678', $bukti->device_id);
        $this->assertNotNull($bukti->client_captured_at);
    }

    /** "false" dari Dart harus terbaca sebagai liveness gagal, bukan lolos. */
    public function test_liveness_false_dari_kawat_terbaca_gagal(): void
    {
        $this->post('/api/attendances', $this->payloadKawat([
            'liveness_passed' => 'false',
        ]))->assertStatus(422);

        $this->assertSame(0, Attendance::count());
    }

    /**
     * Flag integritas perangkat juga dikirim sebagai "true"/"false".
     * Perangkat yang mengaku ter-root harus tetap terbaca server.
     */
    public function test_flag_integritas_perangkat_terbaca(): void
    {
        $response = $this->post('/api/attendances', $this->payloadKawat([
            'is_rooted' => 'true',
        ]));

        // Diterima atau ditolak middleware -- yang penting BUKAN 422 karena
        // validasi gagal membaca "true" sebagai boolean.
        $this->assertNotSame(
            422,
            $response->status(),
            'flag "true" dari Dart ditolak validasi: klien & server tidak sinkron',
        );
    }

    /**
     * Notasi kurung siku multipart (liveness_challenges[0], [1], ...) harus
     * sampai sebagai array di server, bukan tiga field terpisah yang terbuang.
     */
    public function test_array_tantangan_liveness_terkirim_utuh(): void
    {
        $this->post('/api/attendances', $this->payloadKawat([
            'liveness_challenges' => ['smile', 'turnRight'],
        ]))->assertStatus(201);

        $this->assertSame(
            ['smile', 'turnRight'],
            AttendanceEvidence::firstOrFail()->liveness_challenges,
        );
    }

    /**
     * Aplikasi lama tidak mengirim field bukti sama sekali. Payload-nya harus
     * tetap diterima selama kebijakan belum diwajibkan.
     */
    public function test_payload_aplikasi_lama_tetap_diterima(): void
    {
        config(['attendance.evidence_required' => false]);

        $this->post('/api/attendances', [
            'latitude'  => '-6.2',
            'longitude' => '106.816666',
            'foto'      => UploadedFile::fake()->image('absen.jpg', 400, 400),
        ])->assertStatus(201);

        $this->assertTrue(AttendanceEvidence::firstOrFail()->is_flagged);
    }
}

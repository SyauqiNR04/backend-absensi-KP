<?php
/*
|==================================================================
| FITUR: Uji Kewajiban foto_bukti pada Absensi
| Membuktikan aturan "absensi harus punya bukti foto" kini dijamin basis data,
| bukan hanya disepakati lapisan aplikasi.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FotoBuktiRequiredTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->employee = Employee::create([
            'nip'          => '12345',
            'nama_lengkap' => 'Budi',
            'jabatan'      => 'Staf',
            'password'     => Hash::make('Kj9#mQ2vLx8@Wz4!'),
            'status'       => Employee::STATUS_ACTIVE,
        ]);
    }

    private function baris(array $override = []): array
    {
        return array_merge([
            'employee_id' => $this->employee->id,
            'waktu_absen' => Carbon::now('Asia/Jakarta'),
            'status'      => 'hadir',
            'latitude'    => -6.2,
            'longitude'   => 106.8,
            'foto_bukti'  => 'absensi/ada.jpg',
        ], $override);
    }

    /** INTI: penyisipan langsung tanpa foto ditolak basis data, bukan lolos. */
    public function test_basis_data_menolak_absensi_tanpa_foto(): void
    {
        $this->expectException(QueryException::class);

        // Sengaja lewat query builder, melewati validasi aplikasi -- inilah
        // jalur yang dulu menghasilkan baris tanpa bukti foto.
        DB::table('attendances')->insert($this->baris(['foto_bukti' => null]));
    }

    /** Absensi dengan foto tetap tersimpan normal. */
    public function test_absensi_dengan_foto_tetap_tersimpan(): void
    {
        $absen = Attendance::create($this->baris());

        $this->assertSame('absensi/ada.jpg', $absen->fresh()->foto_bukti);
    }

    /**
     * foto_pulang HARUS tetap nullable: sudah absen masuk tetapi belum absen
     * pulang adalah keadaan sah. Bila ikut diwajibkan, absen masuk tidak akan
     * pernah bisa disimpan.
     */
    public function test_foto_pulang_tetap_boleh_kosong(): void
    {
        $absen = Attendance::create($this->baris(['foto_pulang' => null]));

        $this->assertNull($absen->fresh()->foto_pulang);
        $this->assertNotNull($absen->fresh()->foto_bukti);
    }

    /** Kolomnya benar-benar NOT NULL di skema, bukan sekadar dijaga aplikasi. */
    public function test_kolom_tidak_lagi_nullable_di_skema(): void
    {
        $kolom = collect(Schema::getColumns('attendances'))
            ->firstWhere('name', 'foto_bukti');

        $this->assertNotNull($kolom, 'kolom foto_bukti tidak ditemukan');
        $this->assertFalse($kolom['nullable'], 'foto_bukti masih nullable');
    }
}

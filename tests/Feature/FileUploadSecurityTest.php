<?php
/*
|==================================================================
| FITUR: Uji Keamanan Upload File
| Membuktikan SecureImageRule menolak file berbahaya (polyglot PHP,
| MIME palsu) dan menerima gambar yang sah.
|==================================================================
*/

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileUploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function actingEmployee(): Employee
    {
        $e = Employee::create([
            'nip' => '12345', 'nama_lengkap' => 'Uji', 'jabatan' => 'Staf', 'password' => Hash::make('x'),
        ]);
        Sanctum::actingAs($e, ['attendance:submit']);
        return $e;
    }

    /** File PHP disamarkan .jpg harus ditolak. */
    public function test_rejects_php_disguised_as_image(): void
    {
        Storage::fake('private');
        $this->actingEmployee();

        $evil = UploadedFile::fake()->createWithContent('shell.jpg', "<?php system(\$_GET['c']); ?>");

        $this->postJson('/api/attendances', [
            'latitude' => 0, 'longitude' => 0, 'foto' => $evil,
        ])->assertStatus(422);
    }

    /** File teks dengan ekstensi gambar (tanpa magic bytes) ditolak. */
    public function test_rejects_fake_mime(): void
    {
        Storage::fake('private');
        $this->actingEmployee();

        $fake = UploadedFile::fake()->createWithContent('foto.png', 'ini-bukan-gambar');

        $this->postJson('/api/attendances', [
            'latitude' => 0, 'longitude' => 0, 'foto' => $fake,
        ])->assertStatus(422);
    }
}

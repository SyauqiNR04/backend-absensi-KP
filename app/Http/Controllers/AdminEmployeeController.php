<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;

class AdminEmployeeController
{
    /**
     * Aturan validasi foto referensi wajah.
     *
     * dimensions minimal 300x300: MobileFaceNet menerima input 112x112, jadi
     * wajah pada foto perlu cukup besar sebelum di-crop. Foto mungil hasil
     * screenshot menghasilkan embedding kabur yang menaikkan false reject di
     * lapangan -- kegagalannya baru terlihat saat karyawan gagal absen.
     */
    private const FOTO_RULES = [
        'nullable',
        'image',
        'mimes:jpeg,jpg,png',
        'max:4096',
        'dimensions:min_width=300,min_height=300',
    ];

    private const FOTO_MESSAGES = [
        'foto_referensi.image' => 'Foto referensi harus berupa berkas gambar.',
        'foto_referensi.mimes' => 'Foto referensi harus berformat JPG atau PNG.',
        'foto_referensi.max' => 'Ukuran foto referensi maksimal 4 MB.',
        'foto_referensi.dimensions' => 'Resolusi foto referensi minimal 300x300 piksel agar wajah cukup jelas untuk dikenali.',
    ];

    /**
     * Menyimpan foto ke disk privat (storage/app/private), bukan disk 'public'.
     *
     * Foto wajah adalah data biometrik: menaruhnya di public/storage berarti
     * siapa pun yang menebak nama berkasnya bisa mengunduhnya tanpa login.
     * Nama berkas diacak agar tidak bisa ditebak dari NIP.
     */
    private function simpanFoto(UploadedFile $foto, ?string $fotoLama): string
    {
        $path = $foto->store('face-references', 'local');

        // Ganti foto -> berkas lama tidak lagi dirujuk siapa pun. Dibiarkan,
        // ia menumpuk sebagai data biometrik yatim di disk.
        if ($fotoLama && $fotoLama !== $path) {
            Storage::disk('local')->delete($fotoLama);
        }

        return $path;
    }

    // 1. READ: Menampilkan daftar seluruh karyawan
    public function index()
    {
        $employees = DB::table('employees')->orderBy('id', 'desc')->get();
        return view('admin.employees.index', compact('employees'));
    }

    // 2. CREATE: Menampilkan formulir tambah karyawan baru
    public function create()
    {
        return view('admin.employees.create');
    }

    // 3. STORE: Menangkap data dari formulir dan menyimpannya ke database
    //
    // Password WAJIB diisi di sini: login mobile membutuhkannya, dan admin
    // adalah satu-satunya yang membuat akun (registrasi mandiri ditutup).
    // Tanpa ini karyawan baru tidak akan pernah bisa masuk ke aplikasi.
    public function store(Request $request)
    {
        $request->validate([
            'nip' => 'required|string|max:50|unique:employees,nip',
            'nama_lengkap' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
            'password' => ['required', 'confirmed', Password::defaults()],
            'foto_referensi' => self::FOTO_RULES,
        ], self::FOTO_MESSAGES);

        DB::table('employees')->insert([
            'nip' => $request->nip,
            'nama_lengkap' => $request->nama_lengkap,
            'jabatan' => $request->jabatan,
            'password' => Hash::make($request->password),
            'foto_referensi' => $request->hasFile('foto_referensi')
                ? $this->simpanFoto($request->file('foto_referensi'), null)
                : null,
            'status' => Employee::STATUS_ACTIVE,
            'created_at' => Carbon::now('Asia/Jakarta'),
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return redirect('/admin/employees')->with('success', 'Data karyawan baru berhasil ditambahkan!');
    }

    // 4. EDIT: Menampilkan formulir edit berdasarkan ID karyawan
    public function edit($id)
    {
        $employee = DB::table('employees')->where('id', $id)->first();
        
        if (!$employee) {
            return redirect('/admin/employees')->with('error', 'Data karyawan tidak ditemukan.');
        }

        return view('admin.employees.edit', compact('employee'));
    }

    // 5. UPDATE: Menyimpan perubahan data dari formulir edit ke database
    // Password bersifat opsional di sini: dikosongkan berarti tidak diubah.
    // Diisi berarti reset — dipakai saat karyawan lupa password, dan untuk
    // memberi password awal pada karyawan lama yang datanya dibuat sebelum
    // login berpassword diberlakukan.
    public function update(Request $request, $id)
    {
        $request->validate([
            'nip' => 'required|string|max:50|unique:employees,nip,' . $id,
            'nama_lengkap' => 'required|string|max:255',
            'jabatan' => 'required|string|max:255',
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'foto_referensi' => self::FOTO_RULES,
        ], self::FOTO_MESSAGES);

        $employee = DB::table('employees')->where('id', $id)->first();

        if (! $employee) {
            return redirect('/admin/employees')->with('error', 'Data karyawan tidak ditemukan.');
        }

        $data = [
            'nip' => $request->nip,
            'nama_lengkap' => $request->nama_lengkap,
            'jabatan' => $request->jabatan,
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Kolom foto hanya disentuh bila admin benar-benar mengunggah berkas.
        // Membiarkannya kosong berarti "jangan ubah", sama seperti password --
        // supaya edit jabatan tidak diam-diam menghapus foto yang sudah ada.
        if ($request->hasFile('foto_referensi')) {
            $data['foto_referensi'] = $this->simpanFoto(
                $request->file('foto_referensi'),
                $employee->foto_referensi,
            );
        }

        DB::table('employees')->where('id', $id)->update($data);

        // Password berganti -> seluruh sesi lama dicabut. Kalau tidak, sesi
        // yang mungkin sudah dikuasai orang lain tetap hidup meski password
        // sudah direset.
        if ($request->filled('password')) {
            Employee::find($id)?->tokens()->delete();
        }

        return redirect('/admin/employees')->with('success', 'Data karyawan berhasil diperbarui!');
    }

    // 6. NONAKTIFKAN: Karyawan resign/diberhentikan.
    //
    // Sengaja TIDAK menghapus baris. Tabel attendances memakai
    // onDelete('cascade'), jadi menghapus karyawan ikut menghapus seluruh
    // riwayat absensinya — bukti penggajian yang tidak bisa dikembalikan.
    // Menonaktifkan menutup akses (token dicabut) tanpa membuang riwayat.
    public function destroy($id)
    {
        $employee = Employee::find($id);

        if (! $employee) {
            return redirect('/admin/employees')->with('error', 'Data karyawan tidak ditemukan.');
        }

        $employee->deactivate();

        return redirect('/admin/employees')
            ->with('success', 'Karyawan dinonaktifkan. Riwayat absensinya tetap tersimpan.');
    }

    // 7. AKTIFKAN: mengembalikan akses karyawan (mis. salah nonaktifkan,
    // karyawan kembali bekerja, atau kelak menyetujui pendaftar 'pending').
    public function activate($id)
    {
        $employee = Employee::find($id);

        if (! $employee) {
            return redirect('/admin/employees')->with('error', 'Data karyawan tidak ditemukan.');
        }

        $employee->forceFill(['status' => Employee::STATUS_ACTIVE])->save();

        return redirect('/admin/employees')->with('success', 'Karyawan berhasil diaktifkan kembali!');
    }

    // 8. FOTO: menyajikan foto referensi ke panel admin.
    //
    // Berkasnya ada di disk privat sehingga tidak bisa ditautkan langsung dari
    // <img src>. Rute ini berada di dalam middleware 'auth', jadi hanya admin
    // yang sudah login yang dapat melihatnya.
    public function photo($id): StreamedResponse
    {
        $employee = DB::table('employees')->where('id', $id)->first();

        abort_if(! $employee || ! $employee->foto_referensi, 404);
        abort_unless(Storage::disk('local')->exists($employee->foto_referensi), 404);

        return Storage::disk('local')->response($employee->foto_referensi, null, [
            'Cache-Control' => 'no-store',
        ]);
    }

    // 9. HAPUS FOTO: mencabut foto referensi tanpa menghapus karyawannya.
    //
    // Dibutuhkan saat admin salah unggah foto orang lain -- tanpa ini satu-
    // satunya jalan keluar adalah menimpanya dengan foto lain.
    public function deletePhoto($id)
    {
        $employee = DB::table('employees')->where('id', $id)->first();

        if (! $employee) {
            return redirect('/admin/employees')->with('error', 'Data karyawan tidak ditemukan.');
        }

        if ($employee->foto_referensi) {
            Storage::disk('local')->delete($employee->foto_referensi);
            DB::table('employees')->where('id', $id)->update([
                'foto_referensi' => null,
                'updated_at' => Carbon::now('Asia/Jakarta'),
            ]);
        }

        return redirect('/admin/employees/' . $id . '/edit')
            ->with('success', 'Foto referensi dihapus. Karyawan ini tidak akan bisa absen sampai foto baru diunggah.');
    }
}
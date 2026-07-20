<?php
/*
|==================================================================
| FITUR: Tinjauan Bukti Verifikasi Wajah
| Menampilkan absensi yang buktinya janggal agar admin dapat menindaklanjuti.
|==================================================================
*/

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceEvidence;
use App\Services\Security\FaceEvidenceValidator as F;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AdminVerificationController
 * -------------------------------------------------------------------------
 * Bukti verifikasi sudah tersimpan sejak absensi dikirim, tetapi tanpa halaman
 * ini datanya tidak pernah terlihat siapa pun -- dan bukti yang tidak pernah
 * dibaca sama saja dengan tidak dikumpulkan.
 *
 * Halaman ini SENGAJA tidak menyediakan aksi "setujui/tolak". Absensinya sudah
 * tercatat; yang dibutuhkan admin adalah melihat mana yang perlu ditanyakan ke
 * karyawan, bukan menyunting riwayat. Menyediakan tombol ubah pada data yang
 * berfungsi sebagai bukti justru menghapus nilainya sebagai bukti.
 */
class AdminVerificationController
{
    /**
     * Penjelasan tiap penanda dalam bahasa yang bisa ditindaklanjuti admin,
     * bukan istilah teknis mentah dari basis data.
     */
    public const PENJELASAN = [
        F::FLAG_MISSING => [
            'judul'   => 'Tanpa bukti verifikasi',
            'arti'    => 'Aplikasi tidak mengirim hasil pencocokan wajah sama sekali.',
            'tindak'  => 'Biasanya aplikasi versi lama. Minta karyawan memperbarui aplikasi.',
            'berat'   => true,
        ],
        F::FLAG_LIVENESS_MISSING => [
            'judul'   => 'Tanpa hasil deteksi keaslian',
            'arti'    => 'Aplikasi tidak melaporkan apakah uji keaslian wajah dijalankan.',
            'tindak'  => 'Sama seperti di atas: perbarui aplikasi karyawan.',
            'berat'   => false,
        ],
        F::FLAG_SCORE_BELOW_POLICY => [
            'judul'   => 'Skor kemiripan di bawah ambang',
            'arti'    => 'Wajah tidak cukup mirip dengan foto referensi.',
            'tindak'  => 'Absensi ini ditolak. Bila berulang pada karyawan yang sama, foto referensinya mungkin sudah tidak mewakili (ganti gaya rambut, berkacamata).',
            'berat'   => true,
        ],
        F::FLAG_THRESHOLD_TAMPERED => [
            'judul'   => 'Ambang aplikasi dilonggarkan',
            'arti'    => 'Aplikasi memakai ambang yang lebih longgar dari yang sah -- tanda aplikasi dimodifikasi.',
            'tindak'  => 'Tindak lanjuti langsung: minta karyawan memasang ulang aplikasi resmi.',
            'berat'   => true,
        ],
        F::FLAG_LIVENESS_FAILED => [
            'judul'   => 'Uji keaslian wajah gagal',
            'arti'    => 'Aplikasi melaporkan uji keaslian tidak lolos namun absensi tetap dikirim.',
            'tindak'  => 'Absensi ini ditolak. Wajar bila sesekali (pencahayaan buruk); mencurigakan bila sering.',
            'berat'   => true,
        ],
        F::FLAG_IMPLAUSIBLE_SCORE => [
            'judul'   => 'Skor terlalu sempurna',
            'arti'    => 'Skor nyaris 100%, praktis mustahil dari dua pengambilan gambar berbeda.',
            'tindak'  => 'Indikasi skor dikarang atau foto referensi dibandingkan dengan dirinya sendiri.',
            'berat'   => true,
        ],
        F::FLAG_REPEATED_CHALLENGE => [
            'judul'   => 'Urutan uji keaslian terulang',
            'arti'    => 'Urutan gerakan yang diminta sama persis dengan absensi sebelumnya, padahal diacak tiap sesi.',
            'tindak'  => 'Indikasi rekaman lama diputar ulang. Perhatikan bila terjadi berulang.',
            'berat'   => true,
        ],
        F::FLAG_CLOCK_SKEW => [
            'judul'   => 'Jam perangkat menyimpang',
            'arti'    => 'Waktu di HP jauh berbeda dari waktu server.',
            'tindak'  => 'Sering kali hanya zona waktu salah set. Minta karyawan mengaktifkan waktu otomatis.',
            'berat'   => false,
        ],
        F::FLAG_DEVICE_CHANGED => [
            'judul'   => 'Absen dari perangkat berbeda',
            'arti'    => 'Instalasi aplikasi berbeda dari absensi sebelumnya.',
            'tindak'  => 'Wajar bila karyawan ganti HP atau pasang ulang aplikasi. Curigai bila berganti-ganti terus.',
            'berat'   => false,
        ],
    ];

    public function index(Request $request)
    {
        $filter = $request->input('flag');

        $query = AttendanceEvidence::with(['employee', 'attendance'])
            ->where('is_flagged', true)
            ->orderByDesc('id');

        // Filter per jenis temuan. whereJsonContains dipakai karena flags
        // disimpan sebagai array JSON, bukan kolom terpisah.
        if ($filter && array_key_exists($filter, self::PENJELASAN)) {
            $query->whereJsonContains('flags', $filter);
        }

        $evidences = $query->paginate(20)->withQueryString();

        // Jumlah per jenis temuan untuk chip filter di atas tabel. Dihitung
        // dari seluruh baris bertanda, bukan hanya halaman yang tampil.
        $jumlah = [];
        foreach (AttendanceEvidence::where('is_flagged', true)->pluck('flags') as $flags) {
            foreach ((array) $flags as $f) {
                $jumlah[$f] = ($jumlah[$f] ?? 0) + 1;
            }
        }

        return view('admin.verifikasi.index', [
            'evidences'  => $evidences,
            'jumlah'     => $jumlah,
            'filter'     => $filter,
            'penjelasan' => self::PENJELASAN,
            'totalBersih' => AttendanceEvidence::where('is_flagged', false)->count(),
        ]);
    }

    /**
     * Menyajikan foto absensi ke panel admin berdasarkan id ABSENSI.
     *
     * Dipakai halaman Riwayat Absensi, yang sebelumnya menautkan
     * asset('storage/...') -- alamat disk publik, padahal foto absensi sudah
     * dipindah ke disk privat. Akibatnya seluruh foto baru tampil rusak di
     * panel admin, persis seperti yang terjadi di aplikasi.
     */
    public function attendancePhoto($id, string $type): StreamedResponse
    {
        abort_unless(in_array($type, ['masuk', 'pulang'], true), 404);

        $absen = Attendance::find($id);
        $path = $absen?->{$type === 'pulang' ? 'foto_pulang' : 'foto_bukti'};

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Menyajikan foto absensi (bukan foto referensi) agar admin bisa
     * membandingkan sendiri wajah yang terekam saat absen.
     *
     * Berkasnya di disk privat, dan rute ini ada di dalam middleware 'auth'.
     */
    public function photo($evidenceId): StreamedResponse
    {
        $bukti = AttendanceEvidence::with('attendance')->find($evidenceId);

        abort_if(! $bukti || ! $bukti->attendance, 404);

        // Satu baris absensi memuat dua event; fotonya di kolom berbeda.
        $path = $bukti->type === 'pulang'
            ? $bukti->attendance->foto_pulang
            : $bukti->attendance->foto_bukti;

        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'no-store',
        ]);
    }
}

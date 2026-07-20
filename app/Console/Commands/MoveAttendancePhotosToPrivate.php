<?php
/*
|==================================================================
| PERKAKAS: Pemindahan Foto Absensi ke Disk Privat
| Memindahkan sisa foto wajah dari disk publik (dapat diunduh tanpa login)
| ke disk privat, tanpa mengubah satu baris pun di basis data.
|==================================================================
*/

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * MoveAttendancePhotosToPrivate
 * -------------------------------------------------------------------------
 * Foto absensi dulu disimpan di disk publik, sehingga siapa pun yang menebak
 * nama berkasnya dapat mengunduh foto wajah karyawan tanpa login. Sejak
 * pengetatan, foto baru disimpan di disk privat -- tetapi berkas lama tetap
 * tertinggal dan tetap terbuka.
 *
 * Path di basis data bersifat relatif terhadap disk (mis. "absensi/x.jpg"),
 * jadi memindahkan berkas ke path relatif YANG SAMA di disk privat membuatnya
 * langsung terbaca endpoint foto tanpa perlu menyentuh basis data. Tidak
 * mengubah data adalah bagian dari rancangan, bukan kebetulan: pemindahan yang
 * tidak menyunting riwayat jauh lebih mudah dibatalkan bila ada yang keliru.
 *
 * Berkas yatim (tidak dirujuk baris absensi mana pun) ikut dipindahkan. Ia
 * tetap foto wajah seseorang; tidak dirujuk basis data bukan alasan untuk
 * membiarkannya dapat diunduh publik.
 *
 * Jalankan --dry-run lebih dulu untuk melihat rencananya tanpa mengubah apa pun.
 */
class MoveAttendancePhotosToPrivate extends Command
{
    protected $signature = 'absensi:pindahkan-foto-privat {--dry-run : Tampilkan rencana tanpa memindahkan berkas}';

    protected $description = 'Memindahkan foto absensi yang tersisa di disk publik ke disk privat';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $publik = Storage::disk('public');
        $privat = Storage::disk('local');

        // Hanya folder foto absensi. Disk publik bisa saja dipakai hal lain di
        // kemudian hari; menyapu seluruh disk akan memindahkan yang bukan urusan
        // perintah ini.
        $berkas = collect(['absensi', 'attendances'])
            ->flatMap(fn (string $dir) => $publik->exists($dir) ? $publik->allFiles($dir) : [])
            ->values();

        if ($berkas->isEmpty()) {
            $this->info('Tidak ada foto tersisa di disk publik. Tidak ada yang perlu dipindahkan.');
            return self::SUCCESS;
        }

        // Path yang benar-benar dirujuk basis data -- dipakai hanya untuk
        // melaporkan, bukan untuk menyaring.
        $dirujuk = DB::table('attendances')
            ->select('foto_bukti', 'foto_pulang')
            ->get()
            ->flatMap(fn ($r) => [$r->foto_bukti, $r->foto_pulang])
            ->filter()
            ->unique()
            ->flip();

        $this->info(($dryRun ? '[UJI COBA] ' : '') . 'Ditemukan ' . $berkas->count() . ' berkas di disk publik:');
        $this->newLine();

        $dipindah = 0;
        $bentrok = [];
        $gagal = [];

        foreach ($berkas as $path) {
            $status = $dirujuk->has($path) ? 'dirujuk absensi' : 'yatim';

            // Bentrok nama = ada berkas BERBEDA dengan path sama di tujuan.
            // Menimpanya akan menghapus foto absensi lain secara diam-diam,
            // jadi berkas seperti ini dilewati dan dilaporkan untuk diperiksa.
            if ($privat->exists($path)) {
                $bentrok[] = $path;
                $this->line("  <fg=yellow>BENTROK</> {$path} ({$status}) -- sudah ada di disk privat, dilewati");
                continue;
            }

            if ($dryRun) {
                $this->line("  <fg=cyan>akan dipindah</> {$path} ({$status})");
                $dipindah++;
                continue;
            }

            // Salin dulu, verifikasi, baru hapus sumbernya. Kalau memakai move
            // dan penulisan gagal di tengah, fotonya hilang dari kedua disk.
            $isi = $publik->get($path);
            $privat->put($path, $isi);

            if (! $privat->exists($path) || $privat->get($path) !== $isi) {
                $gagal[] = $path;
                $this->line("  <fg=red>GAGAL</> {$path} -- sumber tidak dihapus");
                continue;
            }

            $publik->delete($path);
            $this->line("  <fg=green>dipindah</> {$path} ({$status})");
            $dipindah++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->info("Uji coba selesai: {$dipindah} berkas akan dipindahkan, tidak ada yang diubah.");
            $this->comment('Jalankan tanpa --dry-run untuk benar-benar memindahkan.');
        } else {
            $this->info("Selesai: {$dipindah} berkas dipindahkan ke disk privat.");
            $this->comment('Basis data tidak diubah -- path relatifnya tetap sama.');
        }

        if ($bentrok !== []) {
            $this->warn(count($bentrok) . ' berkas dilewati karena namanya sudah ada di disk privat. Periksa manual sebelum menghapus sumbernya.');
        }

        if ($gagal !== []) {
            $this->error(count($gagal) . ' berkas gagal disalin. Berkas sumbernya sengaja TIDAK dihapus.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

<?php
/*
|==================================================================
| FITUR: Validasi Aman File Upload
| Mencegah LFI/RCE/injeksi malware pada foto absensi via allowlist ekstensi, MIME nyata (finfo), magic bytes, getimagesize, dan anti-polyglot.
|==================================================================
*/

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * SecureImageRule
 * -------------------------------------------------------------------------
 * Validasi berlapis (defense-in-depth) untuk file foto absensi.
 *
 * Rule bawaan `image|mimes:jpeg,png,jpg` hanya memeriksa ekstensi + MIME
 * yang DITEBAK dari ekstensi. Penyerang bisa me-rename `shell.php` menjadi
 * `shell.jpg`, atau menyisipkan payload PHP di metadata gambar (polyglot)
 * untuk mencoba RCE/LFI. Rule ini menutup celah tersebut dengan:
 *   1. Allowlist ekstensi (bukan blocklist).
 *   2. MIME asli dari isi file (finfo), bukan dari ekstensi.
 *   3. Magic bytes / file signature pada byte pertama.
 *   4. getimagesize() -> memastikan benar-benar gambar yang dapat di-decode.
 *   5. Anti-polyglot: tolak jika ada tag PHP/script tertanam di byte stream.
 */
class SecureImageRule implements ValidationRule
{
    private array $allowedExtensions = ['jpg', 'jpeg', 'png'];
    private array $allowedMimeTypes  = ['image/jpeg', 'image/png'];

    private array $magicBytes = [
        'jpeg' => ["\xFF\xD8\xFF"],
        'png'  => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('File foto tidak valid atau gagal diunggah.');
            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());
        if (! in_array($extension, $this->allowedExtensions, true)) {
            $fail('Ekstensi file tidak diizinkan. Hanya JPG/PNG.');
            return;
        }

        $realMime = $value->getMimeType();
        if (! in_array($realMime, $this->allowedMimeTypes, true)) {
            $fail('Tipe konten file tidak sesuai (MIME mismatch).');
            return;
        }

        if (! $this->hasValidSignature($value->getRealPath(), $realMime)) {
            $fail('Signature file tidak cocok. File dicurigai dimanipulasi.');
            return;
        }

        if (@getimagesize($value->getRealPath()) === false) {
            $fail('File bukan gambar yang valid.');
            return;
        }

        if ($this->containsPhpTag($value->getRealPath())) {
            $fail('File mengandung kode yang tidak diizinkan.');
            return;
        }
    }

    private function hasValidSignature(string $path, string $mime): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }
        $header = fread($handle, 8);
        fclose($handle);

        $key = $mime === 'image/png' ? 'png' : 'jpeg';
        foreach ($this->magicBytes[$key] as $signature) {
            if (str_starts_with($header, $signature)) {
                return true;
            }
        }
        return false;
    }

    private function containsPhpTag(string $path): bool
    {
        // Baca 512KB pertama; cukup mendeteksi payload polyglot tanpa
        // memuat seluruh file besar ke memori.
        $content = @file_get_contents($path, false, null, 0, 512 * 1024);
        if ($content === false) {
            return true; // fail-closed
        }
        return preg_match('/<\?php|<\?=|<script/i', $content) === 1;
    }
}

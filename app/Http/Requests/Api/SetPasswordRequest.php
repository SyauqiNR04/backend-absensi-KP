<?php
/*
|==================================================================
| FITUR: Kebijakan Kata Sandi
| Menegakkan password kuat (min 12, campuran, simbol) dan menolak password yang sudah bocor (Have I Been Pwned).
|==================================================================
*/

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * SetPasswordRequest
 * -------------------------------------------------------------------------
 * Kebijakan kata sandi (OWASP ASVS 2.1). Dipakai admin saat set/reset
 * password karyawan atau saat karyawan mengganti password sendiri.
 *
 *  - min 12 karakter, campuran huruf besar/kecil, angka, dan simbol.
 *  - ->uncompromised(): cek terhadap database kebocoran (Have I Been Pwned,
 *    via k-anonymity — hanya prefix hash yang dikirim, aman privasi).
 *  - konfirmasi 'password_confirmation' wajib cocok.
 *
 * CATATAN: rehash & penyimpanan hash dilakukan dengan Hash::make() (driver
 * argon2id dari config/hashing.php). Jangan pernah simpan plain text.
 */
class SetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'password' => [
                'required',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}

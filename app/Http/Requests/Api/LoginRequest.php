<?php
/*
|==================================================================
| FITUR: Validasi Input Login
| Mewajibkan kredensial password (bukan hanya NIP) dan membatasi bentuk/charset input untuk mempersempit permukaan serangan.
|==================================================================
*/

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * LoginRequest
 * -------------------------------------------------------------------------
 * TEMUAN KRITIS: /login versi lama HANYA meminta `nip` lalu langsung
 * menerbitkan token Sanctum. Siapa pun yang tahu NIP seseorang bisa login
 * sebagai orang itu -> BROKEN AUTHENTICATION (OWASP A07).
 * FormRequest ini mewajibkan kredensial `password`. Rate limiting ditangani
 * di route (middleware `throttle`).
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nip'      => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9\-\.]+$/'],
            'password' => ['required', 'string', 'min:6', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nip.regex'         => 'Format NIP tidak valid.',
            'password.required' => 'Password wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['nip' => trim((string) $this->input('nip'))]);
    }
}

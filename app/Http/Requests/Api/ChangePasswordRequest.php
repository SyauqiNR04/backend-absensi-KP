<?php
/*
|==================================================================
| FITUR: Validasi Ganti Password
| Memverifikasi password lama dan menegakkan kebijakan password baru
| yang kuat (min 12, campuran, simbol, tidak bocor).
|==================================================================
*/

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                'different:current_password',
                Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'password.different' => 'Password baru harus berbeda dari password lama.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ];
    }
}

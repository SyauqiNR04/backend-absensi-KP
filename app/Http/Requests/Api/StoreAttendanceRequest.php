<?php
/*
|==================================================================
| FITUR: Validasi Input Absensi
| Validasi koordinat & file foto berlapis (termasuk SecureImageRule) plus penampungan flag integritas perangkat dari klien.
|==================================================================
*/

namespace App\Http\Requests\Api;

use App\Rules\SecureImageRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreAttendanceRequest
 * -------------------------------------------------------------------------
 * Memindahkan validasi absensi keluar dari controller (SRP) dan memperketat
 * validasi file foto. Selain rule bawaan Laravel, kita tambahkan
 * SecureImageRule (magic bytes, MIME nyata, anti-polyglot).
 *
 * Field integritas perangkat (is_rooted, is_emulator, is_mock_location)
 * dikirim oleh klien Flutter dan divalidasi di sini; penolakan final
 * dilakukan di controller / middleware DeviceIntegrity.
 */
class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Sudah dilindungi middleware auth:sanctum di route.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            // Lapis 1: rule bawaan (cepat, murah).
            // Lapis 2: SecureImageRule (mendalam).
            'foto' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:2048', new SecureImageRule()],

            // Sinyal integritas perangkat dari klien (boolean).
            'is_rooted'        => ['sometimes', 'boolean'],
            'is_emulator'      => ['sometimes', 'boolean'],
            'is_mock_location' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'foto.max'   => 'Ukuran foto maksimal 2MB.',
            'foto.image' => 'File yang diunggah harus berupa gambar.',
        ];
    }
}

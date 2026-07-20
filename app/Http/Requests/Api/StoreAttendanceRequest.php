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

    /**
     * Menormalkan boolean yang datang sebagai teks.
     *
     * multipart/form-data tidak mengenal tipe selain teks, dan `bool.toString()`
     * di Dart menghasilkan "true"/"false" -- sementara rule `boolean` Laravel
     * hanya menerima true, false, 1, 0, "1", "0". Tanpa penerjemahan ini SETIAP
     * absensi dari aplikasi ditolak 422, meski seluruh logika server benar.
     *
     * Hanya kunci yang benar-benar dikirim yang disentuh: menambahkan kunci
     * yang tidak ada akan mengubah "bukti tidak dikirim" (yang harus ditandai)
     * menjadi seolah-olah ada.
     */
    protected function prepareForValidation(): void
    {
        $boolFields = [
            'is_rooted', 'is_emulator', 'is_mock_location', 'liveness_passed',
        ];

        $normalized = [];
        foreach ($boolFields as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);
            if (is_string($value)) {
                $lower = strtolower(trim($value));
                if (in_array($lower, ['true', 'false'], true)) {
                    $normalized[$field] = $lower === 'true';
                }
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
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

            // --- Bukti verifikasi wajah (dinilai FaceEvidenceValidator) ---
            //
            // Semua 'sometimes': ketiadaannya BUKAN error validasi melainkan
            // temuan kebijakan, supaya aplikasi lama menerima pesan "perbarui
            // aplikasi" yang jelas alih-alih 422 yang membingungkan.
            //
            // Yang divalidasi di sini hanya bentuknya (rentang & tipe) agar
            // nilai sampah tidak masuk basis data; kelayakannya dinilai
            // belakangan oleh kebijakan server.
            // 'nullable' selain 'sometimes': klien boleh menghilangkan field
            // ATAU mengirimnya bernilai null; keduanya sama-sama berarti
            // "tidak ada bukti" dan ditangani kebijakan, bukan error validasi.
            'face_match_score'     => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'face_match_threshold' => ['sometimes', 'nullable', 'numeric', 'between:0,1'],
            'liveness_passed'      => ['sometimes', 'nullable', 'boolean'],
            'liveness_challenges'  => ['sometimes', 'nullable', 'array', 'max:10'],
            'liveness_challenges.*' => ['string', 'max:32'],
            'device_id'            => ['sometimes', 'nullable', 'string', 'max:64'],
            'client_captured_at'   => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Bukti verifikasi yang dikirim klien, dipisahkan dari payload absensi.
     */
    public function evidenceClaim(): array
    {
        return $this->only([
            'face_match_score',
            'face_match_threshold',
            'liveness_passed',
            'liveness_challenges',
            'device_id',
            'client_captured_at',
        ]);
    }

    public function messages(): array
    {
        return [
            'foto.max'   => 'Ukuran foto maksimal 2MB.',
            'foto.image' => 'File yang diunggah harus berupa gambar.',
        ];
    }
}

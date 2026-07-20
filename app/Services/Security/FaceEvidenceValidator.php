<?php
/*
|==================================================================
| FITUR: Penilaian Bukti Verifikasi Wajah
| Menguji kewajaran klaim verifikasi dari klien dan menegakkan ambang milik server.
|==================================================================
*/

namespace App\Services\Security;

use App\Models\AttendanceEvidence;
use Carbon\Carbon;

/**
 * FaceEvidenceValidator
 * -------------------------------------------------------------------------
 * Pencocokan wajah berjalan di aplikasi, jadi server tidak pernah melihat
 * prosesnya -- hanya kesimpulannya. Kelas ini memperlakukan kesimpulan itu
 * sebagai KLAIM, bukan fakta, lalu mengujinya pada tiga tingkat:
 *
 *   1. Kelengkapan  -- klaimnya ada atau hilang sama sekali?
 *   2. Kebijakan    -- skornya memenuhi ambang SERVER (bukan ambang klien)?
 *   3. Kewajaran    -- angkanya masuk akal untuk proses yang sungguh berjalan?
 *
 * BATAS YANG PERLU DISADARI: klien yang di-tamper tetap bisa mengarang skor
 * 0.97 dan liveness 'passed', dan pemeriksaan ini akan meloloskannya. Yang
 * dicapai kelas ini bukan mencegah pemalsuan, melainkan (a) memaksa penyerang
 * mengarang secara aktif alih-alih sekadar menghapus field, (b) menyimpan
 * karangan itu sebagai jejak permanen, dan (c) menangkap pola yang sulit
 * dipalsukan secara konsisten lintas waktu -- urutan liveness yang selalu
 * sama, skor yang tidak pernah bervariasi, jam perangkat yang digeser.
 *
 * Kepastian sesungguhnya hanya bisa datang dari verifikasi ulang sisi server.
 */
class FaceEvidenceValidator
{
    /** Bukti tidak dikirim sama sekali (aplikasi lama atau field dihapus). */
    public const FLAG_MISSING = 'evidence_missing';

    /** Skor di bawah ambang server -> bukan wajah yang sama. */
    public const FLAG_SCORE_BELOW_POLICY = 'score_below_policy';

    /** Ambang klien lebih longgar dari yang diizinkan -> aplikasi dimodifikasi. */
    public const FLAG_THRESHOLD_TAMPERED = 'client_threshold_tampered';

    /** Klien mengaku liveness gagal tapi tetap mengirim absensi. */
    public const FLAG_LIVENESS_FAILED = 'liveness_failed';

    /** Klien tidak melaporkan hasil liveness sama sekali. */
    public const FLAG_LIVENESS_MISSING = 'liveness_missing';

    /** Jam HP menyimpang jauh dari jam server -> replay atau jam digeser. */
    public const FLAG_CLOCK_SKEW = 'clock_skew';

    /** Skor sempurna: hasil membandingkan foto referensi dengan dirinya sendiri. */
    public const FLAG_IMPLAUSIBLE_SCORE = 'implausible_perfect_score';

    /** Urutan tantangan liveness sama persis dengan absensi sebelumnya. */
    public const FLAG_REPEATED_CHALLENGE = 'repeated_liveness_sequence';

    /** Absen dari instalasi aplikasi yang berbeda dari biasanya. */
    public const FLAG_DEVICE_CHANGED = 'device_changed';

    /**
     * Skor >= angka ini praktis mustahil dari dua pengambilan gambar berbeda:
     * pencahayaan, sudut, dan kompresi selalu menyisakan selisih. Nilai
     * setinggi ini menandakan foto referensi dibandingkan dengan dirinya
     * sendiri, atau skornya memang dikarang.
     */
    private const PERFECT_SCORE_FLOOR = 0.9995;

    /**
     * Menilai klaim klien.
     *
     * @param  array  $claim  payload bukti mentah dari request
     * @return array{flags: list<string>, reject_reason: string|null, normalized: array}
     */
    public function evaluate(int $employeeId, array $claim, Carbon $receivedAt): array
    {
        $flags = [];
        $minScore = (float) config('attendance.min_face_match_score');

        $score = isset($claim['face_match_score']) ? (float) $claim['face_match_score'] : null;
        $clientThreshold = isset($claim['face_match_threshold']) ? (float) $claim['face_match_threshold'] : null;
        $livenessPassed = array_key_exists('liveness_passed', $claim) ? (bool) $claim['liveness_passed'] : null;
        $challenges = $claim['liveness_challenges'] ?? null;
        $deviceId = $claim['device_id'] ?? null;

        $capturedAt = null;
        $skew = null;
        if (! empty($claim['client_captured_at'])) {
            try {
                $capturedAt = Carbon::parse($claim['client_captured_at']);
                $skew = (int) abs($capturedAt->diffInSeconds($receivedAt));
            } catch (\Throwable) {
                // Waktu tak terbaca diperlakukan sama seperti tidak dikirim.
                $capturedAt = null;
            }
        }

        // --- 1. Kelengkapan ---------------------------------------------
        if ($score === null) {
            $flags[] = self::FLAG_MISSING;
        }
        if ($livenessPassed === null) {
            $flags[] = self::FLAG_LIVENESS_MISSING;
        }

        // --- 2. Kebijakan server ----------------------------------------
        // Ambang milik server yang dipakai, bukan yang dikirim klien.
        if ($score !== null && $score < $minScore) {
            $flags[] = self::FLAG_SCORE_BELOW_POLICY;
        }

        if ($clientThreshold !== null
            && $clientThreshold < (float) config('attendance.min_client_threshold')) {
            $flags[] = self::FLAG_THRESHOLD_TAMPERED;
        }

        if ($livenessPassed === false) {
            $flags[] = self::FLAG_LIVENESS_FAILED;
        }

        // --- 3. Kewajaran -----------------------------------------------
        if ($score !== null && $score >= self::PERFECT_SCORE_FLOOR) {
            $flags[] = self::FLAG_IMPLAUSIBLE_SCORE;
        }

        if ($skew !== null && $skew > (int) config('attendance.max_clock_skew_seconds')) {
            $flags[] = self::FLAG_CLOCK_SKEW;
        }

        $terakhir = AttendanceEvidence::where('employee_id', $employeeId)
            ->orderByDesc('id')
            ->first();

        // Urutan tantangan diacak tiap sesi. Terulang persis = rekaman yang
        // sama diputar ulang, atau urutannya dikarang dari nilai tetap.
        if ($terakhir && is_array($challenges) && $challenges !== []
            && $terakhir->liveness_challenges === $challenges) {
            $flags[] = self::FLAG_REPEATED_CHALLENGE;
        }

        if ($terakhir && $deviceId && $terakhir->device_id && $terakhir->device_id !== $deviceId) {
            $flags[] = self::FLAG_DEVICE_CHANGED;
        }

        return [
            'flags'         => $flags,
            'reject_reason' => $this->alasanTolak($flags),
            'normalized'    => [
                'face_match_score'    => $score,
                'client_threshold'    => $clientThreshold,
                'server_threshold'    => $minScore,
                'liveness_passed'     => $livenessPassed,
                'liveness_challenges' => is_array($challenges) ? $challenges : null,
                'device_id'           => $deviceId,
                'client_captured_at'  => $capturedAt,
                'server_received_at'  => $receivedAt,
                'clock_skew_seconds'  => $skew,
            ],
        ];
    }

    /**
     * Memisahkan temuan yang membatalkan absensi dari yang sekadar dicatat.
     *
     * Yang MENOLAK hanyalah temuan dengan makna tunggal: wajahnya memang tidak
     * cocok, atau liveness-nya memang gagal. Sisanya (jam bergeser, ganti HP,
     * urutan berulang) punya sebab sah -- karyawan ganti perangkat, zona waktu
     * salah set -- sehingga hanya ditandai untuk ditinjau admin. Menolak
     * absensi sah gara-gara sinyal ambigu jauh lebih merugikan daripada
     * menandainya.
     */
    private function alasanTolak(array $flags): ?string
    {
        if (in_array(self::FLAG_LIVENESS_FAILED, $flags, true)
            && config('attendance.reject_failed_liveness')) {
            return 'Deteksi keaslian wajah tidak lolos. Ulangi absensi.';
        }

        if (in_array(self::FLAG_SCORE_BELOW_POLICY, $flags, true)) {
            return 'Wajah tidak cocok dengan foto referensi Anda.';
        }

        if (in_array(self::FLAG_THRESHOLD_TAMPERED, $flags, true)) {
            return 'Versi aplikasi tidak sah. Pasang ulang aplikasi resmi.';
        }

        // Bukti hilang hanya menolak setelah kebijakan diwajibkan; selama masa
        // peralihan, aplikasi lama masih boleh absen tetapi tercatat ditandai.
        if (in_array(self::FLAG_MISSING, $flags, true)
            && config('attendance.evidence_required')) {
            return 'Aplikasi Anda sudah usang dan tidak mengirim bukti verifikasi wajah. Perbarui aplikasi.';
        }

        return null;
    }
}

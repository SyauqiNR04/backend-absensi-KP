<?php
/*
|==================================================================
| KEBIJAKAN VERIFIKASI ABSENSI
| Ambang penerimaan bukti (evidence) verifikasi wajah ditetapkan DI SERVER,
| bukan di aplikasi. Klien hanya melaporkan skor mentah; yang memutuskan
| lolos atau tidak adalah berkas ini.
|==================================================================
*/

return [

    /*
    | Wajibkan klien menyertakan bukti verifikasi (skor kemiripan + hasil
    | liveness) pada setiap absensi.
    |
    | Dibiarkan FALSE saat peluncuran supaya aplikasi versi lama -- yang belum
    | mengirim bukti -- tidak langsung tertolak. Selama false, absensi tanpa
    | bukti tetap diterima tetapi ditandai 'evidence_missing' agar terlihat di
    | audit. Setelah semua karyawan memperbarui aplikasi, JADIKAN TRUE; selama
    | masih false, penyerang cukup menghapus field bukti untuk melewatinya.
    */
    'evidence_required' => env('ATTENDANCE_EVIDENCE_REQUIRED', false),

    /*
    | Ambang minimum kemiripan wajah (cosine similarity MobileFaceNet).
    |
    | Server tidak memakai ambang yang dikirim klien: klien yang di-tamper bisa
    | mengaku ambangnya 0.01 dan semua wajah "lolos". Skor mentah dari klien
    | diuji ulang terhadap angka ini.
    |
    | 0.80 adalah titik awal. Kalibrasi dengan mengukur FAR/FRR di lapangan:
    | naikkan bila ada wajah asing yang lolos, turunkan bila karyawan asli
    | terlalu sering ditolak.
    */
    'min_face_match_score' => (float) env('ATTENDANCE_MIN_FACE_SCORE', 0.80),

    /*
    | Ambang yang dilaporkan klien tidak boleh lebih longgar dari ini. Klien
    | yang melaporkan ambang di bawahnya berarti aplikasinya sudah dimodifikasi
    | -- absensinya ditandai meski skornya kebetulan tinggi.
    */
    'min_client_threshold' => (float) env('ATTENDANCE_MIN_CLIENT_THRESHOLD', 0.75),

    /*
    | Selisih maksimum (detik) antara waktu tangkap di HP dan waktu terima di
    | server. Selisih besar menandakan jam perangkat digeser atau payload lama
    | diputar ulang (replay).
    */
    'max_clock_skew_seconds' => (int) env('ATTENDANCE_MAX_CLOCK_SKEW', 300),

    /*
    | Wajib lolos liveness. Berbeda dari evidence_required: bila klien MELAPOR
    | liveness gagal, absensi selalu ditolak -- tidak ada alasan sah untuk
    | mengirim absensi yang liveness-nya sendiri dinyatakan gagal.
    */
    'reject_failed_liveness' => env('ATTENDANCE_REJECT_FAILED_LIVENESS', true),

];

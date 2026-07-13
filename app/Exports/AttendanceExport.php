<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class AttendanceExport implements FromCollection, WithHeadings, WithMapping
{
    protected $attendances;

    public function __construct($attendances)
    {
        $this->attendances = $attendances;
    }

    public function collection()
    {
        return $this->attendances;
    }

    // Mengatur isi data di baris Excel
    public function map($absen): array
    {
        return [
            $absen->employee_nip ?? '-',
            $absen->employee_nama ?? '-',
            Carbon::parse($absen->waktu_absen)->translatedFormat('d F Y H:i:s'),
            ucfirst($absen->status),
            ($absen->latitude && $absen->longitude) ? $absen->latitude . ', ' . $absen->longitude : 'Tidak ada lokasi',
        ];
    }

    // Mengatur judul header kolom paling atas di Excel
    public function headings(): array
    {
        return [
            'NIP Karyawan',
            'Nama Lengkap',
            'Waktu Absensi',
            'Status Kehadiran',
            'Titik Koordinat (Lat, Long)'
        ];
    }
}
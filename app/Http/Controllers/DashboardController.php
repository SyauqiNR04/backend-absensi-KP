<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Exports\AttendanceExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class DashboardController
{
    private function buildFilterQuery(Request $request)
    {
        $tanggal = $request->input('tanggal', Carbon::today('Asia/Jakarta')->toDateString());
        $periode = $request->input('periode', 'harian');
        $status  = $request->input('status', 'semua');
        $search  = $request->input('search');

        $hasEmployeesTable = Schema::hasTable('employees');
        $nameField = 'id'; $nipField = 'id';  

        if ($hasEmployeesTable) {
            $columns = Schema::getColumnListing('employees');
            foreach (['name', 'nama', 'nama_lengkap', 'full_name'] as $field) { if (in_array($field, $columns)) { $nameField = $field; break; } }
            foreach (['nip', 'nip_karyawan', 'employee_code', 'id'] as $field) { if (in_array($field, $columns)) { $nipField = $field; break; } }
        }

        $query = DB::table('attendances');

        if ($hasEmployeesTable) {
            $query->leftJoin('employees', 'attendances.employee_id', '=', 'employees.id')
                  ->select('attendances.*', "employees.{$nipField} as employee_nip", "employees.{$nameField} as employee_nama");
        } else {
            $query->select('attendances.*', 'attendances.employee_id as employee_nip', DB::raw("CONCAT('Karyawan #', attendances.employee_id) as employee_nama"));
        }

        if ($periode == 'harian') {
            $query->whereDate('waktu_absen', $tanggal);
        } elseif ($periode == 'mingguan') {
            $query->whereBetween('waktu_absen', [Carbon::parse($tanggal)->startOfWeek(), Carbon::parse($tanggal)->endOfWeek()]);
        } elseif ($periode == 'bulanan') {
            $query->whereMonth('waktu_absen', Carbon::parse($tanggal)->month)->whereYear('waktu_absen', Carbon::parse($tanggal)->year);
        } elseif ($periode == 'tahunan') {
            $query->whereYear('waktu_absen', Carbon::parse($tanggal)->year);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search, $hasEmployeesTable, $nipField, $nameField) {
                if ($hasEmployeesTable) {
                    $q->where("employees.{$nipField}", 'like', "%{$search}%")->orWhere("employees.{$nameField}", 'like', "%{$search}%");
                } else {
                    $q->where('attendances.employee_id', 'like', "%{$search}%");
                }
            });
        }

        if ($status !== 'semua') { $query->where('attendances.status', $status); }

        return $query->orderBy('attendances.waktu_absen', 'desc');
    }

    public function summary(Request $request)
    {
        $query = $this->buildFilterQuery($request);
        $attendances = $query->get();

        $totalData  = $attendances->count();
        $onTime     = $attendances->where('status', 'hadir')->count();
        $terlambat  = $attendances->where('status', 'terlambat')->count();
        
        $persentaseHadir = $totalData > 0 ? round(($onTime / $totalData) * 100, 1) : 0;
        $persentaseTelat = $totalData > 0 ? round(($terlambat / $totalData) * 100, 1) : 0;

        $recentActivities = $query->take(5)->get();

        // ==========================================
        // LOGIKA PENGELOMPOKAN DATA UNTUK GRAFIK (CHART)
        // ==========================================
        $periode = $request->input('periode', 'harian');
        
        // Setup Label dan Array kosong
        $chartLabels = [];
        if ($periode == 'harian') $chartLabels = ['07.00', '09.00', '11.00', '13.00', '15.00', '17.00'];
        elseif ($periode == 'mingguan') $chartLabels = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        elseif ($periode == 'bulanan') $chartLabels = ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'];
        elseif ($periode == 'tahunan') $chartLabels = ['Bln 1', 'Bln 2', 'Bln 3', 'Bln 4', 'Bln 5', 'Bln 6', 'Bln 7', 'Bln 8', 'Bln 9', 'Bln 10', 'Bln 11', 'Bln 12'];

        $count = count($chartLabels);
        $dataHadir = array_fill(0, $count, 0);
        $dataTelat = array_fill(0, $count, 0);
        $dataIzin  = array_fill(0, $count, 0);
        $dataAlpha = array_fill(0, $count, 0);

        // Masukkan data ke kotak yang sesuai
        foreach ($attendances as $absen) {
            $date = Carbon::parse($absen->waktu_absen);
            $idx = 0;

            if ($periode == 'harian') {
                $hour = $date->hour;
                if ($hour < 9) $idx = 0; elseif ($hour < 11) $idx = 1; elseif ($hour < 13) $idx = 2; elseif ($hour < 15) $idx = 3; elseif ($hour < 17) $idx = 4; else $idx = 5;
            } elseif ($periode == 'mingguan') {
                $day = $date->dayOfWeekIso; // 1(Senin) - 7(Minggu)
                if ($day <= 5) $idx = $day - 1; else continue; // Skip Sabtu Minggu jika ada
            } elseif ($periode == 'bulanan') {
                $day = $date->day;
                if ($day <= 7) $idx = 0; elseif ($day <= 14) $idx = 1; elseif ($day <= 21) $idx = 2; else $idx = 3;
            } elseif ($periode == 'tahunan') {
                $idx = $date->month - 1;
            }

            // Tambahkan nilai ke status yang cocok
            $status = strtolower($absen->status);
            if (str_contains($status, 'hadir')) $dataHadir[$idx]++;
            elseif (str_contains($status, 'terlambat')) $dataTelat[$idx]++;
            elseif (str_contains($status, 'izin')) $dataIzin[$idx]++;
            else $dataAlpha[$idx]++;
        }

        return view('admin.dashboard', compact(
            'totalData', 'persentaseHadir', 'persentaseTelat', 'recentActivities', 
            'chartLabels', 'dataHadir', 'dataTelat', 'dataIzin', 'dataAlpha'
        ));
    }

    public function riwayat(Request $request)
    {
        $query = $this->buildFilterQuery($request);
        $attendances = $query->paginate(10)->withQueryString();
        $totalData = $attendances->total(); 
        
        $tanggal = $request->input('tanggal', Carbon::today('Asia/Jakarta')->toDateString());
        $periode = $request->input('periode', 'harian');
        $status  = $request->input('status', 'semua');
        $search  = $request->input('search');

        return view('admin.riwayat', compact('attendances', 'tanggal', 'periode', 'status', 'search', 'totalData'));
    }

    public function export(Request $request, $type)
    {
        $query = $this->buildFilterQuery($request);
        $attendances = $query->get();
        $timestamp = Carbon::now('Asia/Jakarta')->format('Ymd_His');

        if ($type === 'excel') return Excel::download(new AttendanceExport($attendances), "Laporan_Absensi_{$timestamp}.xlsx");
        if ($type === 'pdf') {
            $pdf = Pdf::loadView('admin.exports.pdf', compact('attendances'));
            return $pdf->download("Laporan_Absensi_{$timestamp}.pdf");
        }
        return back();
    }
}
@extends('admin.layouts.app')
@section('title', 'Summary Dashboard - Admin')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div style="display: flex; flex-direction: column; gap: 24px; max-width: 1100px;">
    
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <div style="color: #14422D; font-size: 30px; font-weight: 700;">Tren Kehadiran Pegawai</div>
            <div style="color: #414943; font-size: 16px;">Visualisasi data statistik kehadiran secara komprehensif.</div>
        </div>
        
        @php $currentPeriode = request('periode', 'harian'); @endphp
        <div style="padding: 6px; background: #EFF4FF; border-radius: 16px; border: 1px solid rgba(192, 201, 193, 0.20); display: flex; align-items: center;">
            <a href="{{ request()->fullUrlWithQuery(['periode' => 'harian']) }}" class="btn-hover" style="padding: 8px 20px; border-radius: 12px; font-size: 12px; font-weight: 700; color: {{ $currentPeriode == 'harian' ? '#14422D' : '#414943' }}; background: {{ $currentPeriode == 'harian' ? 'white' : 'transparent' }}; box-shadow: {{ $currentPeriode == 'harian' ? '0 1px 2px rgba(0,0,0,0.05)' : 'none' }};">Harian</a>
            <a href="{{ request()->fullUrlWithQuery(['periode' => 'mingguan']) }}" class="btn-hover" style="padding: 8px 20px; border-radius: 12px; font-size: 12px; font-weight: 700; color: {{ $currentPeriode == 'mingguan' ? '#14422D' : '#414943' }}; background: {{ $currentPeriode == 'mingguan' ? 'white' : 'transparent' }}; box-shadow: {{ $currentPeriode == 'mingguan' ? '0 1px 2px rgba(0,0,0,0.05)' : 'none' }};">Mingguan</a>
            <a href="{{ request()->fullUrlWithQuery(['periode' => 'bulanan']) }}" class="btn-hover" style="padding: 8px 20px; border-radius: 12px; font-size: 12px; font-weight: 700; color: {{ $currentPeriode == 'bulanan' ? '#14422D' : '#414943' }}; background: {{ $currentPeriode == 'bulanan' ? 'white' : 'transparent' }}; box-shadow: {{ $currentPeriode == 'bulanan' ? '0 1px 2px rgba(0,0,0,0.05)' : 'none' }};">Bulanan</a>
            <a href="{{ request()->fullUrlWithQuery(['periode' => 'tahunan']) }}" class="btn-hover" style="padding: 8px 20px; border-radius: 12px; font-size: 12px; font-weight: 700; color: {{ $currentPeriode == 'tahunan' ? '#14422D' : '#414943' }}; background: {{ $currentPeriode == 'tahunan' ? 'white' : 'transparent' }}; box-shadow: {{ $currentPeriode == 'tahunan' ? '0 1px 2px rgba(0,0,0,0.05)' : 'none' }};">Tahunan</a>
        </div>
    </div>

    <div style="padding: 32px; background: white; border-radius: 40px; box-shadow: 0px 4px 24px rgba(45, 90, 67, 0.06); border: 1px solid rgba(192, 201, 193, 0.15); display: flex; flex-direction: column; gap: 32px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; gap: 32px;">
                <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 12px; height: 12px; background: #14422D; border-radius: 50%;"></div><div style="color: #0B1C30; font-size: 12px; font-weight: 700;">Hadir</div></div>
                <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 12px; height: 12px; background: #F4BE47; border-radius: 50%;"></div><div style="color: #0B1C30; font-size: 12px; font-weight: 700;">Terlambat</div></div>
                <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 12px; height: 12px; background: #D3E4FE; border-radius: 50%;"></div><div style="color: #0B1C30; font-size: 12px; font-weight: 700;">Izin</div></div>
                <div style="display: flex; align-items: center; gap: 8px;"><div style="width: 12px; height: 12px; background: #BA1A1A; border-radius: 50%;"></div><div style="color: #0B1C30; font-size: 12px; font-weight: 700;">Alpha</div></div>
            </div>
        </div>
        
        <div style="height: 300px; width: 100%;">
            <canvas id="attendanceChart"></canvas>
        </div>
    </div>

    <div style="display: flex; gap: 24px;">
        <div style="flex: 1; padding: 24px; background: #14422D; border-radius: 32px; color: white; display: flex; flex-direction: column; gap: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between;">
                <div style="width: 26px; height: 16px; background: #F4BE47;"></div>
                <div style="padding: 4px 8px; background: rgba(255,255,255,0.2); border-radius: 4px; font-size: 10px; font-weight: 700;">+2.4%</div>
            </div>
            <div style="font-size: 30px; font-weight: 700; margin-top: 8px;">{{ $persentaseHadir }}%</div>
            <div style="font-size: 12px; opacity: 0.8;">Rata-rata Kehadiran</div>
        </div>
        <div style="flex: 1; padding: 24px; background: white; border-radius: 32px; border: 1px solid rgba(192,201,193,0.2); display: flex; flex-direction: column; gap: 8px;">
            <div style="display: flex; justify-content: space-between;">
                <div style="width: 26px; height: 26px; background: #7A5900;"></div>
                <div style="padding: 4px 8px; background: rgba(255,218,214,0.2); border-radius: 4px; color: #BA1A1A; font-size: 10px; font-weight: 700;">-1.2%</div>
            </div>
            <div style="font-size: 30px; font-weight: 700; color: #14422D; margin-top: 8px;">{{ $persentaseTelat }}%</div>
            <div style="font-size: 12px; color: #414943;">Tren Keterlambatan (%)</div>
        </div>
        <div style="flex: 1; padding: 24px; background: white; border-radius: 32px; border: 1px solid rgba(192,201,193,0.2); display: flex; flex-direction: column; gap: 8px;">
            <div style="width: 21px; height: 28px; background: #14422D;"></div>
            <div style="font-size: 20px; font-weight: 600; color: #14422D; margin-top: 8px; line-height: 1.2;">Divisi IT &<br>Security</div>
            <div style="font-size: 12px; color: #414943;">Departemen Paling Disiplin</div>
        </div>
        <div style="flex: 1; padding: 24px; background: #DCE9FF; border-radius: 32px; border: 1px solid rgba(192,201,193,0.2); display: flex; flex-direction: column; gap: 8px;">
            <div style="width: 29px; height: 21px; background: #14422D;"></div>
            <div style="font-size: 30px; font-weight: 700; color: #14422D; margin-top: 8px;">{{ number_format($totalData) }}</div>
            <div style="font-size: 12px; color: #414943;">Total Data Log Aktif</div>
        </div>
    </div>

    <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 10px;">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0 16px;">
            <div style="font-size: 20px; font-weight: 600; color: #14422D;">Aktivitas Terbaru</div>
            <a href="/admin/riwayat" class="btn-hover" style="font-size: 12px; font-weight: 700; color: #14422D;">Lihat Semua Laporan</a>
        </div>
        
        <div style="background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.2); overflow: hidden; box-shadow: 0px 4px 12px rgba(45,90,67,0.08);">
            <div style="display: flex; background: #E5EEFF; border-bottom: 1px solid rgba(192, 201, 193, 0.3); padding: 16px 24px; font-size: 12px; font-weight: 600; color: #0B1C30;">
                <div style="width: 50px;">NO</div>
                <div style="flex: 2;">NIP KARYAWAN</div>
                <div style="flex: 2;">WAKTU ABSEN</div>
                <div style="flex: 1.5;">STATUS</div>
                <div style="flex: 2;">KOORDINAT</div>
            </div>
            
            @forelse($recentActivities as $index => $absen)
                @php $isHadir = str_contains(strtolower($absen->status), 'hadir'); @endphp
                <div class="table-row" style="display: flex; padding: 20px 24px; border-bottom: 1px solid rgba(192, 201, 193, 0.1); align-items: center;">
                    <div style="width: 50px; color: #414943; font-size: 14px;">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
                    <div style="flex: 2; font-weight: 700; color: #14422D;">{{ $absen->employee_nip }}</div>
                    <div style="flex: 2; color: #0B1C30; font-size: 14px;">
                        {{ \Carbon\Carbon::parse($absen->waktu_absen)->translatedFormat('d M Y') }} 
                        <br><span style="color: #414943; font-size: 12px;">{{ \Carbon\Carbon::parse($absen->waktu_absen)->format('H:i:s') }} WIB</span>
                    </div>
                    <div style="flex: 1.5;">
                        <span style="padding: 4px 12px; background: {{ $isHadir ? 'rgba(188,238,207,0.3)' : 'rgba(255,222,161,0.3)' }}; border-radius: 999px; border: 1px solid {{ $isHadir ? '#BCEECF' : '#FFDEA1' }}; color: {{ $isHadir ? '#14422D' : '#7A5900' }}; font-size: 12px; font-weight: 700;">{{ ucfirst($absen->status) }}</span>
                    </div>
                    <div style="flex: 2; font-family: monospace; font-size: 12px; color: #304D3D; background: #EFF4FF; padding: 4px 8px; border-radius: 4px; display: inline-block;">{{ $absen->latitude ?? '-' }}, {{ $absen->longitude ?? '-' }}</div>
                </div>
            @empty
                <div style="padding: 40px; text-align: center; color: #6B7280;">Belum ada aktivitas.</div>
            @endforelse
        </div>
    </div>
</div>

<script>
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    
    // Menerima data dari Controller
    const labels = @json($chartLabels);
    const dataHadir = @json($dataHadir);
    const dataTelat = @json($dataTelat);
    const dataIzin = @json($dataIzin);
    const dataAlpha = @json($dataAlpha);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label: 'Hadir', data: dataHadir, borderColor: '#14422D', backgroundColor: '#14422D', tension: 0.4, borderWidth: 3, pointRadius: 4 },
                { label: 'Terlambat', data: dataTelat, borderColor: '#F4BE47', backgroundColor: '#F4BE47', tension: 0.4, borderWidth: 3, pointRadius: 4 },
                { label: 'Izin', data: dataIzin, borderColor: '#D3E4FE', backgroundColor: '#D3E4FE', tension: 0.4, borderWidth: 3, pointRadius: 4, borderDash: [5, 5] },
                { label: 'Alpha', data: dataAlpha, borderColor: '#BA1A1A', backgroundColor: '#BA1A1A', tension: 0.4, borderWidth: 3, pointRadius: 4, borderDash: [5, 5] }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } }, // Legend disembunyikan karena sudah ada UI kustom di atas
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(192, 201, 193, 0.2)' } },
                x: { grid: { display: false } }
            },
            interaction: { mode: 'index', intersect: false }
        }
    });
</script>
@endsection
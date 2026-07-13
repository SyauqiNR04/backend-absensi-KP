@extends('admin.layouts.app')
@section('title', 'Riwayat Absensi - Admin')

@section('content')
<div style="display: flex; flex-direction: column; gap: 24px;">
    <!-- Header -->
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <div style="color: #14422D; font-size: 30px; font-weight: 700;">Log Riwayat Absensi Karyawan</div>
        <div style="color: #414943; font-size: 16px;">Memantau data kehadiran harian secara penuh (Max 10 Riwayat per Halaman).</div>
    </div>

    <!-- Memanggil Filter Bar dari folder partials Anda -->
    @include('admin.partials.filter-bar')

    <!-- Tabel Lengkap -->
    <div style="background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); box-shadow: 0px 4px 12px rgba(45, 90, 67, 0.08); overflow: hidden; display: flex; flex-direction: column;">
        
        <div style="background: #E5EEFF; border-bottom: 1px solid rgba(192, 201, 193, 0.30); display: flex;">
            <div style="width: 67px; padding: 20px 24px; color: #0B1C30; font-size: 12px; font-weight: 600;">NO</div>
            <div style="flex: 2; padding: 20px 24px; color: #0B1C30; font-size: 12px; font-weight: 600;">NIP / NAMA</div>
            <div style="flex: 2; padding: 20px 24px; color: #0B1C30; font-size: 12px; font-weight: 600;">WAKTU ABSEN</div>
            <div style="flex: 1.5; padding: 20px 24px; color: #0B1C30; font-size: 12px; font-weight: 600;">STATUS</div>
            <div style="flex: 2; padding: 20px 24px; color: #0B1C30; font-size: 12px; font-weight: 600;">KOORDINAT</div>
            <div style="flex: 1; padding: 20px 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-align: center;">FOTO</div>
        </div>

        <div style="display: flex; flex-direction: column;">
            @forelse($attendances as $index => $absen)
                @php
                    $bgStatus = str_contains(strtolower($absen->status), 'hadir') ? 'rgba(188, 238, 207, 0.30)' : (str_contains(strtolower($absen->status), 'terlambat') ? 'rgba(255, 222, 161, 0.30)' : 'rgba(255, 218, 214, 0.40)');
                    $borderStatus = str_contains(strtolower($absen->status), 'hadir') ? '#BCEECF' : (str_contains(strtolower($absen->status), 'terlambat') ? '#FFDEA1' : '#FFDAD6');
                    $textStatus = str_contains(strtolower($absen->status), 'hadir') ? '#14422D' : (str_contains(strtolower($absen->status), 'terlambat') ? '#7A5900' : '#BA1A1A');
                @endphp
                <div class="table-row" style="border-top: 1px solid rgba(192, 201, 193, 0.10); display: flex; align-items: center; padding: 12px 0;">
                    <!-- Angka Nomor Mengikuti Halaman (Misal: Hal 2 mulai dari 11) -->
                    <div style="width: 67px; padding: 0 24px; color: #414943; font-size: 16px;">{{ $attendances->firstItem() + $index }}</div>
                    
                    <div style="flex: 2; padding: 0 24px;">
                        <div style="color: #2563EB; font-size: 16px; font-weight: 700;">{{ $absen->employee_nip }}</div>
                        <div style="color: #6B7280; font-size: 13px;">{{ $absen->employee_nama }}</div>
                    </div>
                    
                    <div style="flex: 2; padding: 0 24px;">
                        <div style="color: #0B1C30; font-size: 16px;">{{ \Carbon\Carbon::parse($absen->waktu_absen)->translatedFormat('d M Y') }}</div>
                        <div style="color: #414943; font-size: 14px;">{{ \Carbon\Carbon::parse($absen->waktu_absen)->format('H:i:s') }} WIB</div>
                    </div>
                    
                    <div style="flex: 1.5; padding: 0 24px;">
                        <div style="display: inline-flex; padding: 6px 12px; background: {{ $bgStatus }}; border-radius: 9999px; border: 1px solid {{ $borderStatus }}; align-items: center; gap: 6px;">
                            <div style="width: 10px; height: 10px; background: {{ $textStatus }}; border-radius: 50%;"></div>
                            <div style="color: {{ $textStatus }}; font-size: 12px; font-weight: 700;">{{ ucfirst($absen->status) }}</div>
                        </div>
                    </div>
                    
                    <div style="flex: 2; padding: 0 24px;">
                        <div style="padding: 6px 8px; background: #EFF4FF; border-radius: 4px; color: #304D3D; font-size: 12px; font-family: monospace;">{{ $absen->latitude ?? '-' }}<br/>{{ $absen->longitude ?? '-' }}</div>
                    </div>
                    
                    <div style="flex: 1; padding: 0 24px; display: flex; justify-content: center;">
                        <div style="width: 48px; height: 48px; border-radius: 8px; border: 2px solid {{ $borderStatus }}; overflow: hidden;">
                            @if($absen->foto_bukti)
                                <a href="{{ asset('storage/' . $absen->foto_bukti) }}" target="_blank"><img src="{{ asset('storage/' . $absen->foto_bukti) }}" style="width: 100%; height: 100%; object-fit: cover;"/></a>
                            @else
                                <div style="width: 100%; height: 100%; background: #C0C9C1; display: flex; align-items: center; justify-content: center;"><span style="color: white; font-size: 10px;">No Pic</span></div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div style="padding: 60px; text-align: center; color: #6B7280; font-size: 18px;">Belum ada riwayat absensi.</div>
            @endforelse
        </div>

        <!-- AREA PAGINATION (GANTI HALAMAN LANJUTAN) -->
        <div style="padding: 16px 24px; background: #EFF4FF; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(192, 201, 193, 0.30);">
            <div style="color: #414943; font-size: 14px;">
                Menampilkan <b>{{ $attendances->firstItem() ?? 0 }}</b> sampai <b>{{ $attendances->lastItem() ?? 0 }}</b> dari total <b>{{ $attendances->total() }}</b> entri
            </div>
            
            <div style="display: flex; gap: 8px;">
                @if ($attendances->onFirstPage())
                    <div style="padding: 10px 16px; border-radius: 12px; border: 1px solid #C0C9C1; color: #9CA3AF; cursor: not-allowed; font-weight: bold; background: white;">Sebelumnya</div>
                @else
                    <a href="{{ $attendances->previousPageUrl() }}" class="btn-hover" style="padding: 10px 16px; border-radius: 12px; border: 1px solid #14422D; color: #14422D; font-weight: bold; background: white;">Sebelumnya</a>
                @endif
                
                @if ($attendances->hasMorePages())
                    <a href="{{ $attendances->nextPageUrl() }}" class="btn-hover" style="padding: 10px 16px; background: #14422D; border-radius: 12px; color: white; font-weight: bold;">Selanjutnya &gt;</a>
                @else
                    <div style="padding: 10px 16px; border-radius: 12px; border: 1px solid #C0C9C1; background: #E5E7EB; color: #9CA3AF; cursor: not-allowed; font-weight: bold;">Selanjutnya &gt;</div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
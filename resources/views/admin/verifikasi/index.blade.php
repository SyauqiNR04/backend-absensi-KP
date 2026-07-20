@extends('admin.layouts.app')
@section('title', 'Tinjauan Verifikasi Wajah - Admin')

@section('content')
<div style="display: flex; flex-direction: column; gap: 24px;">

    <div style="display: flex; flex-direction: column; gap: 4px;">
        <div style="color: #14422D; font-size: 30px; font-weight: 700;">Tinjauan Verifikasi Wajah</div>
        <div style="color: #414943; font-size: 16px;">
            Absensi yang bukti verifikasinya perlu diperiksa. Halaman ini untuk ditindaklanjuti, bukan untuk mengubah riwayat.
        </div>
    </div>

    {{-- Konteks penting: tanpa pembanding, "12 temuan" tidak berarti apa-apa.
         Angka bersih menunjukkan apakah temuan ini pengecualian atau justru pola. --}}
    <div style="padding: 16px 20px; background: #EFF4FF; border-radius: 12px; border: 1px solid #C0C9C1; color: #0B1C30; font-size: 14px; line-height: 20px;">
        <strong>{{ $evidences->total() }} bukti ditandai</strong> dari total
        {{ $evidences->total() + $totalBersih }} bukti absensi yang tercatat
        ({{ $totalBersih }} tanpa temuan).
        <br>
        Penandaan bukan tuduhan: sebagian punya sebab sah seperti ganti HP atau jam perangkat salah set.
        Yang perlu diwaspadai adalah pola yang berulang pada karyawan yang sama.
    </div>

    @if(!empty($jumlah))
        <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
            <a href="{{ route('admin.verifikasi') }}" class="btn-hover"
               style="padding: 8px 16px; border-radius: 9999px; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid {{ $filter ? '#C0C9C1' : '#2D5A43' }}; background: {{ $filter ? 'white' : '#2D5A43' }}; color: {{ $filter ? '#414943' : 'white' }};">
                Semua
            </a>
            @foreach($jumlah as $kode => $n)
                @php $info = $penjelasan[$kode] ?? null; @endphp
                @if($info)
                    <a href="{{ route('admin.verifikasi', ['flag' => $kode]) }}" class="btn-hover"
                       style="padding: 8px 16px; border-radius: 9999px; font-size: 13px; font-weight: 700; text-decoration: none; border: 1px solid {{ $filter === $kode ? '#2D5A43' : '#C0C9C1' }}; background: {{ $filter === $kode ? '#2D5A43' : 'white' }}; color: {{ $filter === $kode ? 'white' : ($info['berat'] ? '#BA1A1A' : '#92400E') }};">
                        {{ $info['judul'] }} ({{ $n }})
                    </a>
                @endif
            @endforeach
        </div>
    @endif

    <div style="background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); box-shadow: 0px 4px 12px rgba(45, 90, 67, 0.08); overflow: hidden; display: flex; flex-direction: column;">

        <div style="background: #E5EEFF; border-bottom: 1px solid rgba(192, 201, 193, 0.30); display: flex; align-items: flex-start;">
            <div style="width: 90px; padding: 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase;">FOTO</div>
            <div style="width: 230px; padding: 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase;">KARYAWAN &amp; WAKTU</div>
            <div style="width: 130px; padding: 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase;">SKOR WAJAH</div>
            <div style="flex: 1; padding: 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase;">TEMUAN &amp; TINDAK LANJUT</div>
        </div>

        <div style="display: flex; flex-direction: column;">
            @forelse($evidences as $bukti)
                <div class="table-row" style="border-top: 1px solid rgba(192, 201, 193, 0.10); display: flex; align-items: flex-start;">

                    <div style="width: 90px; padding: 20px 24px;">
                        {{-- Foto absen, bukan foto referensi: admin perlu melihat
                             wajah yang benar-benar terekam saat absensi itu. --}}
                        <img src="{{ route('admin.verifikasi.photo', $bukti->id) }}" alt="Foto absensi"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                             style="width: 56px; height: 56px; object-fit: cover; border-radius: 12px; border: 1px solid #C0C9C1;">
                        <div style="display: none; width: 56px; height: 56px; border-radius: 12px; border: 1px dashed #C0C9C1; align-items: center; justify-content: center; color: #6B7280; font-size: 10px; text-align: center;">
                            tidak ada
                        </div>
                    </div>

                    <div style="width: 230px; padding: 20px 24px; display: flex; flex-direction: column; gap: 4px;">
                        <div style="color: #14422D; font-size: 15px; font-weight: 700;">
                            {{ $bukti->employee?->nama_lengkap ?? 'Karyawan dihapus' }}
                        </div>
                        <div style="color: #6B7280; font-size: 12px; font-family: monospace;">
                            NIP: {{ $bukti->employee?->nip ?? '-' }}
                        </div>
                        <div style="color: #414943; font-size: 13px; margin-top: 4px;">
                            {{ ucfirst($bukti->type) }} &middot;
                            {{ $bukti->server_received_at?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB
                        </div>
                        @if($bukti->device_id)
                            <div style="color: #9CA3AF; font-size: 11px; font-family: monospace;" title="Identitas instalasi aplikasi">
                                {{ \Illuminate\Support\Str::limit($bukti->device_id, 12) }}
                            </div>
                        @endif
                    </div>

                    <div style="width: 130px; padding: 20px 24px;">
                        @if($bukti->face_match_score !== null)
                            @php
                                $lolos = $bukti->server_threshold !== null
                                    && $bukti->face_match_score >= $bukti->server_threshold;
                            @endphp
                            <div style="color: {{ $lolos ? '#14422D' : '#BA1A1A' }}; font-size: 20px; font-weight: 700;">
                                {{ number_format($bukti->face_match_score * 100, 1) }}%
                            </div>
                            <div style="color: #6B7280; font-size: 12px;">
                                ambang {{ number_format(($bukti->server_threshold ?? 0) * 100, 0) }}%
                            </div>
                        @else
                            <div style="color: #BA1A1A; font-size: 14px; font-weight: 700;">Tidak ada</div>
                        @endif
                    </div>

                    <div style="flex: 1; padding: 20px 24px; display: flex; flex-direction: column; gap: 12px;">
                        @foreach($bukti->flags ?? [] as $kode)
                            @php $info = $penjelasan[$kode] ?? null; @endphp
                            @if($info)
                                <div style="padding: 12px 16px; border-radius: 12px; border: 1px solid {{ $info['berat'] ? '#FFDAD6' : '#FDE68A' }}; background: {{ $info['berat'] ? 'rgba(255, 218, 214, 0.25)' : 'rgba(254, 243, 199, 0.40)' }};">
                                    <div style="color: {{ $info['berat'] ? '#BA1A1A' : '#92400E' }}; font-size: 14px; font-weight: 700;">
                                        {{ $info['judul'] }}
                                    </div>
                                    <div style="color: #0B1C30; font-size: 13px; line-height: 19px; margin-top: 4px;">
                                        {{ $info['arti'] }}
                                    </div>
                                    <div style="color: #414943; font-size: 13px; line-height: 19px; margin-top: 6px;">
                                        <strong>Tindak lanjut:</strong> {{ $info['tindak'] }}
                                    </div>
                                </div>
                            @else
                                {{-- Penanda baru yang belum punya penjelasan: tampilkan
                                     kodenya apa adanya, jangan disembunyikan. --}}
                                <div style="color: #6B7280; font-size: 13px; font-family: monospace;">{{ $kode }}</div>
                            @endif
                        @endforeach

                        @if($bukti->liveness_challenges)
                            <div style="color: #6B7280; font-size: 12px;">
                                Urutan gerakan: {{ implode(' → ', $bukti->liveness_challenges) }}
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div style="padding: 60px; text-align: center; color: #6B7280; font-size: 18px;">
                    @if($filter)
                        Tidak ada bukti dengan temuan ini.
                    @else
                        Belum ada bukti absensi yang ditandai.
                    @endif
                </div>
            @endforelse
        </div>
    </div>

    @if($evidences->hasPages())
        <div>{{ $evidences->links() }}</div>
    @endif
</div>
@endsection

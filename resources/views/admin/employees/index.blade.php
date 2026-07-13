@extends('admin.layouts.app')
@section('title', 'Manajemen Data Karyawan - Admin')

@section('content')
<div style="display: flex; flex-direction: column; gap: 24px;">

    <div style="display: flex; justify-content: space-between; align-items: flex-end;">
        <div style="display: flex; flex-direction: column; gap: 4px;">
            <div style="color: #14422D; font-size: 30px; font-weight: 700;">Manajemen Data Karyawan</div>
            <div style="color: #414943; font-size: 16px;">Kelola identitas, jabatan, dan status pendaftaran wajah (face recognition).</div>
        </div>
        
        <a href="/admin/employees/create" class="btn-hover" style="padding: 14px 24px; background: #FDC74E; border-radius: 12px; color: #725300; font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">+</span> Tambah Karyawan Baru
        </a>
    </div>

    @if(session('success'))
        <div style="padding: 16px 20px; background: #E6F4EA; border-radius: 12px; border: 1px solid #CEEAD6; color: #137333; font-weight: 600; display: flex; align-items: center; gap: 12px;">
            <div style="width: 20px; height: 20px; background: #137333; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-size: 12px;">✓</div>
            {{ session('success') }}
        </div>
    @endif

    <div style="background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); box-shadow: 0px 4px 12px rgba(45, 90, 67, 0.08); overflow: hidden; display: flex; flex-direction: column;">
        
        <div style="background: #E5EEFF; border-bottom: 1px solid rgba(192, 201, 193, 0.30); display: flex; align-items: flex-start;">
            <div style="width: 67px; padding: 28px 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase;">NO</div>
            <div style="width: 250px; padding: 28px 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase;">NAMA LENGKAP & NIP</div>
            <div style="width: 200px; padding: 28px 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase;">JABATAN</div>
            <div style="width: 180px; padding: 28px 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase;">STATUS WAJAH (AI)</div>
            <div style="flex: 1; padding: 28px 24px; color: #0B1C30; font-size: 12px; font-weight: 600; text-transform: uppercase; text-align: right;">AKSI</div>
        </div>

        <div style="display: flex; flex-direction: column;">
            @forelse($employees as $index => $emp)
                <div class="table-row" style="border-top: 1px solid rgba(192, 201, 193, 0.10); display: flex; align-items: center;">
                    <div style="width: 67px; padding: 30px 24px; color: #414943; font-size: 16px;">
                        {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                    </div>
                    
                    <div style="width: 250px; padding: 20px 24px; display: flex; flex-direction: column;">
                        <div style="color: #14422D; font-size: 16px; font-weight: 700;">{{ $emp->nama_lengkap }}</div>
                        <div style="color: #6B7280; font-size: 13px; font-family: monospace;">NIP: {{ $emp->nip }}</div>
                    </div>
                    
                    <div style="width: 200px; padding: 20px 24px;">
                        <div style="color: #0B1C30; font-size: 16px;">{{ $emp->jabatan }}</div>
                    </div>
                    
                    <div style="width: 180px; padding: 20px 24px;">
                        @if($emp->face_embedding)
                            <div style="display: inline-flex; padding: 6px 12px; background: rgba(188, 238, 207, 0.30); border-radius: 9999px; border: 1px solid #BCEECF; align-items: center; gap: 6px;">
                                <div style="width: 8px; height: 8px; background: #14422D; border-radius: 50%;"></div>
                                <div style="color: #14422D; font-size: 12px; font-weight: 700;">Terdaftar</div>
                            </div>
                        @else
                            <div style="display: inline-flex; padding: 6px 12px; background: rgba(255, 218, 214, 0.40); border-radius: 9999px; border: 1px solid #FFDAD6; align-items: center; gap: 6px;">
                                <div style="width: 8px; height: 8px; background: #BA1A1A; border-radius: 50%;"></div>
                                <div style="color: #BA1A1A; font-size: 12px; font-weight: 700;">Belum Rekam</div>
                            </div>
                        @endif
                    </div>
                    
                    <div style="flex: 1; padding: 20px 24px; display: flex; justify-content: flex-end; gap: 12px;">
                        <a href="/admin/employees/{{ $emp->id }}/edit" class="btn-hover" style="padding: 8px 16px; background: #EFF4FF; border-radius: 8px; color: #2563EB; font-size: 14px; font-weight: 700; border: 1px solid #D3E4FE;">Edit</a>
                        
                        <form action="/admin/employees/{{ $emp->id }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus karyawan ini?')" style="margin: 0;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-hover" style="padding: 8px 16px; background: #FFF0F0; border-radius: 8px; color: #BA1A1A; font-size: 14px; font-weight: 700; border: 1px solid #FFDAD6; cursor: pointer;">Hapus</button>
                        </form>
                    </div>
                </div>
            @empty
                <div style="padding: 60px; text-align: center; color: #6B7280; font-size: 18px;">
                    Belum ada data karyawan. Silakan tambah karyawan baru.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
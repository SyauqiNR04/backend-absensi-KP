@extends('admin.layouts.app')
@section('title', 'Tambah Karyawan - Admin')

@section('content')
<div style="display: flex; flex-direction: column; gap: 24px;">
  <div style="display: flex; flex-direction: column; gap: 16px;">
    <a href="/admin/employees" class="btn-hover" style="color: #6B7280; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
        ← Kembali ke Daftar Karyawan
    </a>
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <div style="color: #14422D; font-size: 30px; font-weight: 700;">Tambah Karyawan Baru</div>
        <div style="color: #414943; font-size: 16px;">Masukkan identitas dasar karyawan. Perekaman wajah dilakukan menyusul via aplikasi.</div>
    </div>
  </div>

  @if ($errors->any())
    <div style="padding: 16px; background: #FFF0F0; border-radius: 12px; border: 1px solid #FFDAD6; color: #BA1A1A;">
        <strong style="display: block; margin-bottom: 8px;">Gagal menyimpan data:</strong>
        <ul style="margin: 0; padding-left: 20px;">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
  @endif

  <div style="max-width: 700px; background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); box-shadow: 0px 4px 12px rgba(45, 90, 67, 0.08); padding: 32px;">
    <form action="/admin/employees" method="POST" style="display: flex; flex-direction: column; gap: 24px;">
        @csrf
        <div>
            <label class="form-label">Nomor Induk Pegawai (NIP)</label>
            <input type="text" name="nip" value="{{ old('nip') }}" class="input-premium" placeholder="Contoh: EMP-2024001" required>
        </div>
        <div>
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap') }}" class="input-premium" placeholder="Masukkan nama lengkap" required>
        </div>
        <div>
            <label class="form-label">Jabatan</label>
            <input type="text" name="jabatan" value="{{ old('jabatan') }}" class="input-premium" placeholder="Contoh: Staff IT, HRD" required>
        </div>
        <div style="padding: 16px; background: #EFF4FF; border-radius: 12px; border: 1px solid #C0C9C1; display: flex; gap: 12px; align-items: flex-start;">
            <div style="color: #2563EB; font-size: 20px;">ℹ️</div>
            <div style="color: #0B1C30; font-size: 14px; line-height: 20px;">
                <strong>Catatan Perekaman AI:</strong> Status wajah (*face recognition*) akan otomatis berubah menjadi <b>"Terdaftar"</b> setelah karyawan melakukan *login* pertama kali di aplikasi *mobile*.
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 16px; margin-top: 16px;">
            <a href="/admin/employees" class="btn-hover" style="padding: 14px 24px; background: white; border-radius: 12px; color: #414943; font-size: 16px; font-weight: 700; border: 1px solid #C0C9C1;">Batal</a>
            <button type="submit" class="btn-hover" style="padding: 14px 32px; background: #2D5A43; border-radius: 12px; color: white; font-size: 16px; font-weight: 700; border: none;">Simpan Data</button>
        </div>
    </form>
  </div>
</div>
@endsection
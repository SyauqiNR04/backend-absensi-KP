@extends('admin.layouts.app')
@section('title', 'Edit Karyawan - Admin')

@section('content')
<div style="display: flex; flex-direction: column; gap: 24px;">
  <div style="display: flex; flex-direction: column; gap: 16px;">
    <a href="/admin/employees" class="btn-hover" style="color: #6B7280; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
        ← Kembali ke Daftar Karyawan
    </a>
    <div style="display: flex; flex-direction: column; gap: 4px;">
        <div style="color: #14422D; font-size: 30px; font-weight: 700;">Edit Data Karyawan</div>
        <div style="color: #414943; font-size: 16px;">Ubah identitas atau jabatan karyawan terdaftar.</div>
    </div>
  </div>

  @if ($errors->any())
    <div style="padding: 16px; background: #FFF0F0; border-radius: 12px; border: 1px solid #FFDAD6; color: #BA1A1A;">
        <strong style="display: block; margin-bottom: 8px;">Gagal memperbarui data:</strong>
        <ul style="margin: 0; padding-left: 20px;">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
  @endif

  <div style="max-width: 700px; background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); box-shadow: 0px 4px 12px rgba(45, 90, 67, 0.08); padding: 32px;">
    <form action="/admin/employees/{{ $employee->id }}" method="POST" style="display: flex; flex-direction: column; gap: 24px;">
        @csrf
        @method('PUT')
        <div>
            <label class="form-label">Nomor Induk Pegawai (NIP)</label>
            <input type="text" name="nip" value="{{ old('nip', $employee->nip) }}" class="input-premium" required>
        </div>
        <div>
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="nama_lengkap" value="{{ old('nama_lengkap', $employee->nama_lengkap) }}" class="input-premium" required>
        </div>
        <div>
            <label class="form-label">Jabatan</label>
            <input type="text" name="jabatan" value="{{ old('jabatan', $employee->jabatan) }}" class="input-premium" required>
        </div>

        <div style="border-top: 1px solid rgba(192, 201, 193, 0.30); padding-top: 24px; display: flex; flex-direction: column; gap: 24px;">
            <div style="color: #14422D; font-size: 18px; font-weight: 700;">Reset Password</div>
            @if (empty($employee->password))
                <div style="padding: 16px; background: #FFF0F0; border-radius: 12px; border: 1px solid #FFDAD6; color: #BA1A1A; font-size: 14px; line-height: 20px;">
                    <strong>Karyawan ini belum punya password</strong> sehingga belum bisa login di aplikasi.
                    Isi kolom di bawah untuk memberinya password.
                </div>
            @endif
            <div>
                <label class="form-label">Password Baru</label>
                <input type="password" name="password" class="input-premium" placeholder="Kosongkan bila tidak ingin mengubah">
                <div style="color: #6B7280; font-size: 13px; margin-top: 6px;">
                    Minimal 12 karakter, mengandung huruf besar &amp; kecil, angka, dan simbol.
                    Mengisi kolom ini akan mengeluarkan karyawan dari semua sesi yang sedang aktif.
                </div>
            </div>
            <div>
                <label class="form-label">Ulangi Password Baru</label>
                <input type="password" name="password_confirmation" class="input-premium" placeholder="Ketik ulang password baru">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 16px; margin-top: 16px;">
            <a href="/admin/employees" class="btn-hover" style="padding: 14px 24px; background: white; border-radius: 12px; color: #414943; font-size: 16px; font-weight: 700; border: 1px solid #C0C9C1;">Batal</a>
            <button type="submit" class="btn-hover" style="padding: 14px 32px; background: #2D5A43; border-radius: 12px; color: white; font-size: 16px; font-weight: 700; border: none;">Simpan Perubahan</button>
        </div>
    </form>
  </div>
</div>
@endsection
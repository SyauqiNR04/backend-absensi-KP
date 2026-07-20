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

  @if(session('success'))
    <div style="padding: 16px 20px; background: #E6F4EA; border-radius: 12px; border: 1px solid #CEEAD6; color: #137333; font-weight: 600;">
        {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div style="padding: 16px; background: #FFF0F0; border-radius: 12px; border: 1px solid #FFDAD6; color: #BA1A1A;">
        <strong style="display: block; margin-bottom: 8px;">Gagal memperbarui data:</strong>
        <ul style="margin: 0; padding-left: 20px;">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
  @endif

  <div style="max-width: 700px; background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); box-shadow: 0px 4px 12px rgba(45, 90, 67, 0.08); padding: 32px;">
    <form action="/admin/employees/{{ $employee->id }}" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 24px;">
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

        <div style="border-top: 1px solid rgba(192, 201, 193, 0.30); padding-top: 24px; display: flex; flex-direction: column; gap: 16px;">
            <div style="color: #14422D; font-size: 18px; font-weight: 700;">Foto Referensi Wajah</div>

            @if ($employee->foto_referensi)
                <div style="display: flex; gap: 20px; align-items: flex-start;">
                    <img src="{{ route('admin.employees.photo', $employee->id) }}" alt="Foto referensi {{ $employee->nama_lengkap }}"
                         style="width: 140px; height: 140px; object-fit: cover; border-radius: 16px; border: 1px solid #C0C9C1;">
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="color: #414943; font-size: 14px; line-height: 20px;">
                            Foto ini yang dipakai aplikasi untuk mencocokkan wajah saat absen.
                            Unggah berkas baru di bawah untuk menggantinya.
                        </div>
                        <button type="submit" form="hapus-foto" class="btn-hover"
                                onclick="return confirm('Hapus foto referensi karyawan ini? Ia tidak akan bisa absen sampai foto baru diunggah.')"
                                style="align-self: flex-start; padding: 8px 16px; background: #FFF0F0; border-radius: 8px; color: #BA1A1A; font-size: 14px; font-weight: 700; border: 1px solid #FFDAD6; cursor: pointer;">
                            Hapus Foto
                        </button>
                    </div>
                </div>
            @else
                <div style="padding: 16px; background: #FFF0F0; border-radius: 12px; border: 1px solid #FFDAD6; color: #BA1A1A; font-size: 14px; line-height: 20px;">
                    <strong>Karyawan ini belum punya foto referensi</strong> sehingga belum bisa melakukan absensi.
                    Unggah fotonya di bawah.
                </div>
            @endif

            <div>
                <label class="form-label">{{ $employee->foto_referensi ? 'Ganti Foto' : 'Unggah Foto' }}</label>
                <input type="file" name="foto_referensi" accept="image/jpeg,image/png" class="input-premium">
                <div style="color: #6B7280; font-size: 13px; margin-top: 6px;">
                    JPG atau PNG, maksimal 4 MB, resolusi minimal 300&times;300 piksel.
                    Wajah menghadap depan, pencahayaan merata, hanya satu orang dalam foto.
                    Kosongkan bila tidak ingin mengubah.
                </div>
            </div>
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

    {{-- Terpisah dari form utama: HTML tidak mengizinkan form bersarang.
         Tombolnya di atas terhubung ke sini lewat atribut form="hapus-foto". --}}
    @if ($employee->foto_referensi)
        <form id="hapus-foto" action="{{ route('admin.employees.photo.destroy', $employee->id) }}" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    @endif
  </div>
</div>
@endsection
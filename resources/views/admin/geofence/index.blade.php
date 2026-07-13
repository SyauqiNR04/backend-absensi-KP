@extends('admin.layouts.app')
@section('title', 'Konfigurasi Sistem & Geo-Fence - Admin')

@section('content')
<div style="display: flex; flex-direction: column; gap: 24px;">
  
  <div style="display: flex; flex-direction: column; gap: 4px;">
    <div style="color: #14422D; font-size: 30px; font-weight: 700;">Konfigurasi Sistem Absensi</div>
    <div style="color: #414943; font-size: 16px;">Tetapkan koordinat kantor, radius validasi lokasi, serta aturan jam masuk untuk penentuan status keterlambatan.</div>
  </div>

  @if(session('success'))
    <div style="padding: 16px 20px; background: #E6F4EA; border-radius: 12px; border: 1px solid #CEEAD6; color: #137333; font-weight: 600; display: flex; align-items: center; gap: 12px;">
        <div style="width: 20px; height: 20px; background: #137333; border-radius: 50%; display: flex; justify-content: center; align-items: center; color: white; font-size: 12px;">✓</div>
        {{ session('success') }}
    </div>
  @endif

  @if ($errors->any())
    <div style="padding: 16px; background: #FFF0F0; border-radius: 12px; border: 1px solid #FFDAD6; color: #BA1A1A;">
        <strong style="display: block; margin-bottom: 8px;">Gagal memperbarui konfigurasi:</strong>
        <ul style="margin: 0; padding-left: 20px;">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
  @endif

  <div style="display: flex; gap: 24px; align-items: flex-start;">
      
      <div style="flex: 1; background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); box-shadow: 0px 4px 12px rgba(45, 90, 67, 0.08); padding: 32px;">
        <form action="/admin/geofence" method="POST" style="display: flex; flex-direction: column; gap: 24px;">
            @csrf
            
            <div style="padding-bottom: 16px; border-bottom: 1px solid rgba(192, 201, 193, 0.2);">
                <div style="color: #14422D; font-size: 18px; font-weight: 700; margin-bottom: 16px;">Pengaturan Geo-Fence (Lokasi)</div>
                <div style="display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label class="form-label">Nama Lokasi / Kantor</label>
                        <input type="text" name="nama_lokasi" value="{{ $setting->nama_lokasi ?? '' }}" class="input-premium" required>
                    </div>

                    <div style="display: flex; gap: 16px;">
                        <div style="flex: 1;">
                            <label class="form-label">Latitude</label>
                            <input type="text" name="latitude" value="{{ $setting->latitude ?? '' }}" class="input-premium" required>
                        </div>
                        <div style="flex: 1;">
                            <label class="form-label">Longitude</label>
                            <input type="text" name="longitude" value="{{ $setting->longitude ?? '' }}" class="input-premium" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Radius Absen Maksimal (Meter)</label>
                        <div style="position: relative;">
                            <input type="number" name="radius_meter" value="{{ $setting->radius_meter ?? '' }}" class="input-premium" required style="padding-right: 80px;">
                            <span style="position: absolute; right: 16px; top: 14px; color: #6B7280; font-weight: 600;">Meter</span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div style="color: #14422D; font-size: 18px; font-weight: 700; margin-bottom: 16px;">Pengaturan Jam Kerja (Logika Keterlambatan)</div>
                <div style="display: flex; gap: 16px;">
                    <div style="flex: 1;">
                        <label class="form-label">Batas Jam Masuk</label>
                        <input type="time" name="jam_masuk" value="{{ $setting->jam_masuk ?? '08:00' }}" class="input-premium" required>
                        <div style="margin-top: 8px; color: #6B7280; font-size: 12px;">Karyawan yang absen setelah jam ini akan otomatis berstatus <b>Terlambat</b>.</div>
                    </div>
                    <div style="flex: 1;">
                        <label class="form-label">Batas Jam Pulang</label>
                        <input type="time" name="jam_pulang" value="{{ $setting->jam_pulang ?? '17:00' }}" class="input-premium" required>
                    </div>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <button type="submit" class="btn-hover" style="padding: 14px 32px; background: #2D5A43; border-radius: 12px; color: white; font-size: 16px; font-weight: 700; border: none; width: 100%;">Simpan Konfigurasi Sistem</button>
            </div>
        </form>
      </div>

      <div style="width: 380px; display: flex; flex-direction: column; gap: 24px;">
          <div style="background: #EFF4FF; border-radius: 32px; border: 1px solid #C0C9C1; padding: 24px; text-align: center;">
              <div style="color: #2563EB; font-size: 40px; margin-bottom: 12px;">🗺️</div>
              <div style="color: #0B1C30; font-size: 18px; font-weight: 700; margin-bottom: 8px;">Lihat di Google Maps</div>
              <div style="color: #414943; font-size: 14px; margin-bottom: 20px; line-height: 1.5;">
                  Pastikan titik koordinat sudah presisi di atap gedung kantor.
              </div>
              @if(isset($setting->latitude) && isset($setting->longitude))
                <a href="https://www.google.com/maps?q={{ str_replace(',', '.', $setting->latitude) }},{{ str_replace(',', '.', $setting->longitude) }}" target="_blank" class="btn-hover" style="padding: 12px 24px; background: white; border-radius: 12px; color: #2563EB; font-weight: 700; border: 1px solid #D3E4FE; display: inline-block;">
                    Buka Peta
                </a>
              @endif
          </div>
      </div>
  </div>
</div>
@endsection
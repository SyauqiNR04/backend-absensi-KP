<form method="GET" action="{{ route('admin.dashboard') }}" style="display: flex; flex-direction: column; gap: 16px; width: 100%;">
    
    <div style="display: flex; justify-content: flex-end; align-items: flex-end; gap: 16px;">
        <div style="padding: 16px 31px; background: #D3E4FE; border-radius: 16px; border: 1px solid rgba(192, 201, 193, 0.20); display: flex; flex-direction: column; align-items: center;">
          <div style="color: #7A5900; font-size: 12px; font-weight: 600; letter-spacing: 0.60px;">DATA DITAMPILKAN</div>
          <div style="color: #14422D; font-size: 24px; font-weight: 600; margin-top: 4px;">{{ $totalData }}</div>
        </div>
        
        <div style="position: relative;" id="export-dropdown-container">
            <button type="button" onclick="document.getElementById('export-menu').classList.toggle('show')" class="btn-hover" style="height: 56px; padding: 0 24px; background: #14422D; border-radius: 12px; display: flex; align-items: center; gap: 12px; border: none; cursor: pointer;">
              <div style="width: 16px; height: 16px; background: white; border-radius: 2px;"></div>
              <div style="color: white; font-size: 16px; font-weight: 600;">Export Data ▾</div>
            </button>
            
            <div id="export-menu" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 8px; background: white; border: 1px solid #C0C9C1; border-radius: 12px; overflow: hidden; min-width: 180px; box-shadow: 0px 4px 12px rgba(0,0,0,0.1); z-index: 50;">
                <a href="{{ route('admin.export', ['type' => 'excel'] + request()->all()) }}" target="_blank" style="display: block; padding: 12px 16px; color: #14422D; border-bottom: 1px solid #eee; text-decoration: none; font-weight: 600;">📥 Excel (.xlsx)</a>
                <a href="{{ route('admin.export', ['type' => 'pdf'] + request()->all()) }}" target="_blank" style="display: block; padding: 12px 16px; color: #BA1A1A; text-decoration: none; font-weight: 600;">📄 Document (.pdf)</a>
            </div>
        </div>
    </div>

    <div style="padding: 16px; background: #EFF4FF; border-radius: 16px; border: 1px solid rgba(192, 201, 193, 0.10); display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
        
        <div style="flex: 1; min-width: 200px; position: relative;">
          <div style="position: absolute; left: 16px; top: 12px; width: 14px; height: 14px; background: #414943; border-radius: 50%;"></div>
          <input type="text" name="search" value="{{ $search }}" placeholder="Cari NIP atau Nama..." style="width: 100%; padding: 10px 16px 10px 40px; background: white; border-radius: 12px; border: 1px solid #C0C9C1; color: #0B1C30; font-size: 15px; font-family: inherit; outline: none;">
        </div>
        
        <select name="status" style="padding: 10px 16px; background: white; border-radius: 12px; border: 1px solid #C0C9C1; color: #0B1C30; font-size: 15px; font-family: inherit; outline: none; cursor: pointer;">
            <option value="semua" {{ $status == 'semua' ? 'selected' : '' }}>Semua Status</option>
            <option value="hadir" {{ $status == 'hadir' ? 'selected' : '' }}>🟢 Hadir / On-Time</option>
            <option value="terlambat" {{ $status == 'terlambat' ? 'selected' : '' }}>🟡 Terlambat</option>
            <option value="izin" {{ $status == 'izin' ? 'selected' : '' }}>🔵 Izin</option>
            <option value="alpha" {{ $status == 'alpha' ? 'selected' : '' }}>🔴 Alpha / Absen</option>
        </select>

        <input type="date" name="tanggal" value="{{ $tanggal }}" style="padding: 10px 16px; background: white; border-radius: 12px; border: 1px solid #C0C9C1; color: #0B1C30; font-size: 15px; font-family: inherit; outline: none; cursor: pointer;">

        <select name="periode" style="padding: 10px 16px; background: white; border-radius: 12px; border: 1px solid #C0C9C1; color: #0B1C30; font-size: 15px; font-family: inherit; outline: none; cursor: pointer;">
            <option value="harian" {{ $periode == 'harian' ? 'selected' : '' }}>Harian</option>
            <option value="mingguan" {{ $periode == 'mingguan' ? 'selected' : '' }}>Mingguan</option>
            <option value="bulanan" {{ $periode == 'bulanan' ? 'selected' : '' }}>Bulanan</option>
            <option value="tahunan" {{ $periode == 'tahunan' ? 'selected' : '' }}>Tahunan</option>
        </select>

        <button type="submit" class="btn-hover" style="padding: 10px 24px; background: #FDC74E; border-radius: 12px; border: none; color: #725300; font-size: 15px; font-weight: 600; cursor: pointer;">
            Terapkan Filter
        </button>
        
        @if($search || $status != 'semua' || $periode != 'harian')
            <a href="{{ route('admin.dashboard') }}" style="color: #BA1A1A; font-size: 14px; text-decoration: underline; margin-left: 8px;">Reset</a>
        @endif
    </div>
</form>

<script>
    // Script kecil untuk menutup dropdown export jika diklik di luar area
    window.onclick = function(event) {
        if (!event.target.matches('.btn-hover') && !event.target.closest('#export-dropdown-container')) {
            var dropdowns = document.getElementsByClassName("show");
            for (var i = 0; i < dropdowns.length; i++) {
                dropdowns[i].classList.remove('show');
            }
        }
    }
</script>
<style> .show { display: block !important; } </style>
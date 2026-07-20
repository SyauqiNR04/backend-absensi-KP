<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel Admin Absensi')</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background-color: #F8F9FF; font-family: 'Manrope', sans-serif; overflow: hidden; }
        * { box-sizing: border-box; }
        .table-row:hover { background-color: #F1F5F9; cursor: default; }
        .btn-hover:hover { opacity: 0.8; cursor: pointer; }
        a { text-decoration: none; }
        
        /* INI CSS FORMULIR YANG KEMARIN HILANG SAYA KEMBALIKAN */
        .input-premium { width: 100%; padding: 14px 16px; background: #F8F9FF; border-radius: 12px; border: 1px solid #C0C9C1; color: #0B1C30; font-size: 16px; outline: none; transition: all 0.3s ease; font-family: inherit; }
        .input-premium:focus { border-color: #2D5A43; background: white; box-shadow: 0 0 0 4px rgba(45, 90, 67, 0.1); }
        .form-label { color: #14422D; font-size: 14px; font-weight: 700; margin-bottom: 8px; display: block; }

        /* NAVBAR TERKUNCI DI ATAS */
        .top-navbar { position: fixed; top: 0; left: 0; width: 100%; height: 64px; background: #F8F9FF; box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05); display: flex; justify-content: space-between; align-items: center; padding: 0 24px; z-index: 50; }
        
        /* SIDEBAR TERKUNCI DI KIRI */
        .sidebar { position: fixed; top: 64px; left: 0; width: 256px; height: calc(100vh - 64px); background: white; border-right: 1px solid rgba(192, 201, 193, 0.20); display: flex; flex-direction: column; justify-content: space-between; padding-top: 24px; overflow-y: auto; z-index: 40; }
        
        /* KONTEN UTAMA FLEKSIBEL */
        .main-content { margin-top: 64px; margin-left: 256px; width: calc(100% - 256px); height: calc(100vh - 64px); padding: 32px; overflow-y: auto; background: linear-gradient(0deg, #F8F9FF 0%, #F8F9FF 100%); }
    </style>
</head>
<body>

  <div class="top-navbar">
    <div style="display: flex; align-items: center; gap: 16px;">
      <div style="padding: 8px; background: #2D5A43; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
        <div style="width: 14px; height: 17px; background: white; border-radius: 2px;"></div>
      </div>
      <div style="color: #14422D; font-size: 24px; font-weight: 600;">Panel Admin Absensi</div>
    </div>
    
    <div style="display: flex; align-items: center; gap: 24px;">
      <div style="padding: 8px 16px; background: #EFF4FF; border-radius: 9999px; outline: 1px solid rgba(192, 201, 193, 0.30); display: flex; align-items: center; gap: 16px;">
        <div style="width: 12px; height: 12px; background: #14422D; border-radius: 50%;"></div>
        <div style="color: #14422D; font-size: 12px; font-weight: 600; letter-spacing: 0.60px;">SERVER STATUS: ACTIVE</div>
      </div>
      <form action="{{ route('logout') }}" method="POST" style="margin: 0;">
          @csrf
          <button type="submit" class="btn-hover" style="background: transparent; border: none; color: #BA1A1A; font-weight: 700; font-size: 14px;">Logout</button>
      </form>
    </div>
  </div>

  <div class="sidebar">
    <div style="padding: 0 16px; display: flex; flex-direction: column; gap: 8px;">
      
      <a href="/admin/dashboard" class="btn-hover" style="padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 16px; {{ request()->is('admin/dashboard') ? 'background: rgba(253, 199, 78, 0.15); border-left: 4px solid #F4BE47;' : '' }}">
        <div style="width: 18px; height: 18px; background: {{ request()->is('admin/dashboard') ? '#14422D' : '#414943' }}; border-radius: 4px;"></div>
        <div style="color: {{ request()->is('admin/dashboard') ? '#14422D' : '#414943' }}; font-size: 12px; font-weight: 600;">Dashboard</div>
      </a>
      
      <a href="/admin/riwayat" class="btn-hover" style="padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 16px; {{ request()->is('admin/riwayat') ? 'background: rgba(253, 199, 78, 0.15); border-left: 4px solid #F4BE47;' : '' }}">
        <div style="width: 16px; height: 20px; background: {{ request()->is('admin/riwayat') ? '#14422D' : '#414943' }}; border-radius: 4px;"></div>
        <div style="color: {{ request()->is('admin/riwayat') ? '#14422D' : '#414943' }}; font-size: 12px; font-weight: 600;">Riwayat Absensi</div>
      </a>

      <a href="/admin/employees" class="btn-hover" style="padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 16px; {{ request()->is('admin/employees*') ? 'background: rgba(253, 199, 78, 0.15); border-left: 4px solid #F4BE47;' : '' }}">
        <div style="width: 22px; height: 16px; background: {{ request()->is('admin/employees*') ? '#14422D' : '#414943' }}; border-radius: 4px;"></div>
        <div style="color: {{ request()->is('admin/employees*') ? '#14422D' : '#414943' }}; font-size: 12px; font-weight: 600;">Manage Employees</div>
      </a>

      {{-- Jumlah temuan ditampilkan di sini: bukti yang tidak pernah dibuka
           sama saja dengan tidak dikumpulkan, jadi angkanya perlu terlihat
           tanpa admin harus membuka halamannya lebih dulu. --}}
      @php $jumlahDitandai = \App\Models\AttendanceEvidence::where('is_flagged', true)->count(); @endphp
      <a href="/admin/verifikasi" class="btn-hover" style="padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 16px; {{ request()->is('admin/verifikasi*') ? 'background: rgba(253, 199, 78, 0.15); border-left: 4px solid #F4BE47;' : '' }}">
        <div style="width: 18px; height: 18px; background: {{ request()->is('admin/verifikasi*') ? '#14422D' : '#414943' }}; border-radius: 4px;"></div>
        <div style="color: {{ request()->is('admin/verifikasi*') ? '#14422D' : '#414943' }}; font-size: 12px; font-weight: 600;">Verifikasi Wajah</div>
        @if($jumlahDitandai > 0)
          <div style="margin-left: auto; min-width: 20px; padding: 2px 7px; background: #BA1A1A; border-radius: 9999px; color: white; font-size: 11px; font-weight: 700; text-align: center;">{{ $jumlahDitandai }}</div>
        @endif
      </a>

      <a href="/admin/geofence" class="btn-hover" style="padding: 12px 16px; border-radius: 12px; display: flex; align-items: center; gap: 16px; {{ request()->is('admin/geofence') ? 'background: rgba(253, 199, 78, 0.15); border-left: 4px solid #F4BE47;' : '' }}">
        <div style="width: 18px; height: 18px; background: {{ request()->is('admin/geofence') ? '#14422D' : '#414943' }}; border-radius: 4px;"></div>
        <div style="color: {{ request()->is('admin/geofence') ? '#14422D' : '#414943' }}; font-size: 12px; font-weight: 600;">Geo-Fence Config</div>
      </a>
    </div>

    <div style="padding: 24px;">
        <div style="padding: 16px; background: #2D5A43; border-radius: 16px; display: flex; flex-direction: column; gap: 4px; position: relative; overflow: hidden;">
          <div style="color: #9FCFB2; font-size: 10px; text-transform: uppercase; letter-spacing: 1px;">ENTERPRISE LICENSE</div>
          <div style="color: #9FCFB2; font-size: 20px; font-weight: 600;">v2.4.0-Stable</div>
          <div style="position: absolute; right: -20px; top: 10px; width: 70px; height: 70px; background: #9FCFB2; opacity: 0.10; border-radius: 50%;"></div>
        </div>
    </div>
  </div>

  <div class="main-content">
      @yield('content')
  </div>

</body>
</html>
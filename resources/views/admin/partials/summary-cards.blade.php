<div style="display: flex; gap: 24px;">
    <div style="flex: 1; padding: 24px; background: #2D5A43; border-radius: 32px; display: flex; flex-direction: column; justify-content: space-between; position: relative; overflow: hidden;">
      <div style="width: 100%; height: 20px; background: #F4BE47; border-radius: 4px;"></div>
      <div style="margin-top: 40px;">
        <div style="color: white; font-size: 20px; font-weight: 600;">Persentase Kehadiran</div>
        <div style="color: white; font-size: 30px; font-weight: 700;">{{ $persentase }}%</div>
      </div>
    </div>
    
    <div style="flex: 1; padding: 24px; background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); display: flex; flex-direction: column; justify-content: space-between;">
      <div style="width: 100%; height: 26px; background: #14422D; border-radius: 4px;"></div>
      <div style="margin-top: 40px;">
        <div style="color: #14422D; font-size: 20px; font-weight: 600;">On-Time (Hadir)</div>
        <div style="color: #0B1C30; font-size: 30px; font-weight: 700;">{{ number_format($onTime) }}</div>
      </div>
    </div>

    <div style="flex: 1; padding: 24px; background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); display: flex; flex-direction: column; justify-content: space-between;">
      <div style="width: 100%; height: 33px; background: #7A5900; border-radius: 4px;"></div>
      <div style="margin-top: 40px;">
        <div style="color: #7A5900; font-size: 20px; font-weight: 600;">Terlambat</div>
        <div style="color: #0B1C30; font-size: 30px; font-weight: 700;">{{ number_format($terlambat) }}</div>
      </div>
    </div>

    <div style="flex: 1; padding: 24px; background: white; border-radius: 32px; border: 1px solid rgba(192, 201, 193, 0.20); display: flex; flex-direction: column; justify-content: space-between;">
      <div style="width: 100%; height: 33px; background: #BA1A1A; border-radius: 4px;"></div>
      <div style="margin-top: 40px;">
        <div style="color: #BA1A1A; font-size: 20px; font-weight: 600;">Alpha / Izin</div>
        <div style="color: #0B1C30; font-size: 30px; font-weight: 700;">{{ number_format($absenSakit) }}</div>
      </div>
    </div>
</div>
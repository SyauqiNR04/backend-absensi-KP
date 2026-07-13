<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Absensi Karyawan</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; color: #14422D; }
        .header p { margin: 5px 0; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 8px; text-align: left; }
        th { background-color: #2D5A43; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>
</head>
<body>

    <div class="header">
        <h2>LAPORAN RIWAYAT ABSENSI KARYAWAN</h2>
        <p>Dicetak pada: {{ \Carbon\Carbon::now('Asia/Jakarta')->translatedFormat('d F Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIP</th>
                <th>Nama Karyawan</th>
                <th>Waktu Absen</th>
                <th>Status</th>
                <th>Koordinat Lokasi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($attendances as $index => $absen)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $absen->employee_nip }}</td>
                <td>{{ $absen->employee_nama }}</td>
                <td>{{ \Carbon\Carbon::parse($absen->waktu_absen)->translatedFormat('d M Y, H:i') }}</td>
                <td>{{ ucfirst($absen->status) }}</td>
                <td>{{ $absen->latitude ? $absen->latitude.','.$absen->longitude : '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
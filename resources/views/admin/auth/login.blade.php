<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel Admin Absensi</title>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background-color: #F8F9FF; font-family: 'Manrope', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        * { box-sizing: border-box; }
        .btn-hover:hover { opacity: 0.9; cursor: pointer; }
        .input-premium { width: 100%; padding: 14px 16px; background: #F8F9FF; border-radius: 12px; border: 1px solid #C0C9C1; color: #0B1C30; font-size: 16px; outline: none; transition: all 0.3s ease; }
        .input-premium:focus { border-color: #2D5A43; background: white; box-shadow: 0 0 0 4px rgba(45, 90, 67, 0.1); }
    </style>
</head>
<body>

    <div style="width: 100%; max-width: 480px; padding: 40px; background: white; border-radius: 32px; box-shadow: 0px 8px 24px rgba(45, 90, 67, 0.08); border: 1px solid rgba(192, 201, 193, 0.20);">
        
        <div style="display: flex; justify-content: center; margin-bottom: 24px;">
            <div style="padding: 16px; background: #2D5A43; border-radius: 20px; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 12px rgba(45,90,67,0.2);">
                <div style="width: 24px; height: 30px; background: white; border-radius: 4px;"></div>
            </div>
        </div>

        <div style="text-align: center; margin-bottom: 32px;">
            <div style="color: #14422D; font-size: 28px; font-weight: 700; margin-bottom: 8px;">Panel Admin Server</div>
            <div style="color: #414943; font-size: 15px;">Silakan masukkan kredensial untuk masuk.</div>
        </div>

        @if ($errors->any())
            <div style="padding: 16px; background: #FFF0F0; border-radius: 12px; border: 1px solid #FFDAD6; color: #BA1A1A; margin-bottom: 24px; font-size: 14px;">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </div>
        @endif

        <form action="/admin/login" method="POST" style="display: flex; flex-direction: column; gap: 20px;">
            @csrf
            <div>
                <label style="color: #14422D; font-size: 14px; font-weight: 700; margin-bottom: 8px; display: block;">Alamat Email</label>
                <input type="email" name="email" value="{{ old('email') }}" class="input-premium" placeholder="admin@perusahaan.com" required>
            </div>

            <div>
                <label style="color: #14422D; font-size: 14px; font-weight: 700; margin-bottom: 8px; display: block;">Kata Sandi</label>
                <input type="password" name="password" class="input-premium" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-hover" style="margin-top: 8px; width: 100%; padding: 16px; background: #FDC74E; border-radius: 12px; color: #725300; font-size: 16px; font-weight: 700; border: none;">
                Masuk ke Dasbor
            </button>
        </form>

        <div style="margin-top: 32px; text-align: center; color: #6B7280; font-size: 12px;">
            Akses sistem ini dilindungi secara ketat.<br>Enterprise License v2.4.0
        </div>
    </div>

</body>
</html>
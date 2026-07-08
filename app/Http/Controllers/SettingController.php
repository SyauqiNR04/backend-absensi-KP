<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        // Mengambil data pengaturan baris pertama dari tabel
        $setting = Setting::first();

        if (!$setting) {
            return response()->json(['message' => 'Pengaturan kantor belum ada di database!'], 404);
        }

        return response()->json([
            'message' => 'Berhasil mengambil titik lokasi kantor',
            'data' => $setting
        ], 200);
    }
}

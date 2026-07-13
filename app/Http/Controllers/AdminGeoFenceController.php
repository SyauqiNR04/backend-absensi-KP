<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminGeoFenceController
{
    public function index()
    {
        $setting = DB::table('settings')->first();
        return view('admin.geofence.index', compact('setting'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'nama_lokasi' => 'required|string|max:255',
            'latitude' => 'required|string',
            'longitude' => 'required|string',
            'radius_meter' => 'required|numeric|min:10',
            'jam_masuk' => 'required',
            'jam_pulang' => 'required',
        ]);

        $lat = str_replace(',', '.', $request->latitude);
        $lng = str_replace(',', '.', $request->longitude);

        DB::table('settings')->where('id', 1)->update([
            'nama_lokasi' => $request->nama_lokasi,
            'latitude' => $lat,
            'longitude' => $lng,
            'radius_meter' => $request->radius_meter,
            'jam_masuk' => $request->jam_masuk,
            'jam_pulang' => $request->jam_pulang,
            'updated_at' => Carbon::now('Asia/Jakarta'),
        ]);

        return redirect('/admin/geofence')->with('success', 'Konfigurasi lokasi dan jam kerja berhasil diperbarui!');
    }
}
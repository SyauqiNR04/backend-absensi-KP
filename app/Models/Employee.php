<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    // Mengizinkan semua kolom diisi melalui API
    protected $guarded = []; 

    // Memberitahu Laravel bahwa kolom face_embedding ini formatnya Array/JSON
    protected $casts = [
        'face_embedding' => 'array',
    ];
}
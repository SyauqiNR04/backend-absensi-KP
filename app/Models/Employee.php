<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // Mengubahnya menjadi tipe Authenticatable
use Laravel\Sanctum\HasApiTokens; // Syarat mutlak untuk API

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $table = 'employees';

    // Mengizinkan semua kolom diisi secara massal (mass assignment)
    protected $guarded = ['id'];
}
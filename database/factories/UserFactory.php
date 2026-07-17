<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * Mengikuti skema users yang sebenarnya: tiap admin terhubung ke satu
 * employee (employee_id NOT NULL + FK). Tidak ada kolom name maupun
 * email_verified_at, jadi keduanya tidak diisi di sini.
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nip = 'ADM-' . fake()->unique()->numerify('#####');

        return [
            'employee_id' => fn () => Employee::create([
                'nip'          => $nip,
                'nama_lengkap' => fake()->name(),
                'jabatan'      => 'Administrator',
            ])->id,
            'nip'            => $nip,
            'email'          => fake()->unique()->safeEmail(),
            'password'       => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }
}

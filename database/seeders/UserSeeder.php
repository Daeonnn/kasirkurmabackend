<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Hapus semua user existing (jika ada)
        User::query()->delete();

        // Buat user admin
        User::create([
            'name' => 'Yose Reza',
            'username' => 'yose',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('adminkurma123'),
            'role_id' => 1,
        ]);

        // Buat user kasir
        User::create([
            'name' => 'Elanda',
            'username' => 'elanda',
            'email' => 'kasir@gmail.com',
            'password' => Hash::make('kasirkurma123'),
            'role_id' => 2,
        ]);
    }
}

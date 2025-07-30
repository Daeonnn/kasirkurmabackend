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
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('123'),
            'role_id' => 1,
        ]);

        // Buat user kasir
        User::create([
            'name' => 'Kasir',
            'username' => 'kasir',
            'email' => 'kasir@gmail.com',
            'password' => Hash::make('123'),
            'role_id' => 2,
        ]);
    }
}

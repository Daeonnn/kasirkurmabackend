<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,    // Role harus di-seed dulu
            UserSeeder::class,    // Baru user setelah role ada
        ]);
    }
}

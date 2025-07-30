<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Jenis;

class JenisSeeder extends Seeder
{
    public function run(): void
    {
        Jenis::insert([
            ['name' => 'Kurma'],
            ['name' => 'Kismis'],
        ]);
    }
}

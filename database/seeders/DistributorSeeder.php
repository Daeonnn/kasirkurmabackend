<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Distributor;

class DistributorSeeder extends Seeder
{
    public function run(): void
    {
        Distributor::insert([
            ['name' => 'PT. Barakah Foods', 'phone' => '081234567890', 'address' => 'Jl. Kurma No. 1'],
        ]);
    }
}

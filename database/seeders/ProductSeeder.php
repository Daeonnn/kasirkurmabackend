<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
   public function run(): void
{
    \App\Models\Product::create([
        'name' => 'Kurma Ajwa',
        'jenis_id' => 1,
        'satuan_id' => 1,
        'distributor_id' => 1,
        'stock' => 100,
        'purchase_price' => 65000,
        'selling_price' => 75000
    ]);
}

}

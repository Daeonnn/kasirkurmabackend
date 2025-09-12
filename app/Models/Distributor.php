<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    use HasFactory;
   

    protected $fillable = [
        'name',
        'phone',
        'address'
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'distributor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Satuan extends Model
{
    use HasFactory;

    protected $table = 'satuan';

    protected $fillable = [
        'name',
    ];

    /**
     * Relasi ke produk
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

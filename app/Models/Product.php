<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_barang',
        'name',
        'photo',
        'jenis_id',
        'satuan_id',
        'distributor_id',
        'stock',
        'selling_price'
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'stock' => 'integer',
        'jenis_id' => 'integer',
        'satuan_id' => 'integer',
        'distributor_id' => 'integer'
    ];

    protected $appends = ['photo_url'];

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    public function deletePhoto()
    {
        if ($this->photo && Storage::disk('public')->exists($this->photo)) {
            Storage::disk('public')->delete($this->photo);
        }
    }

    public function jenis()
    {
        return $this->belongsTo(Jenis::class, 'jenis_id');
    }

    public function satuan()
    {
        return $this->belongsTo(Satuan::class, 'satuan_id');
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class, 'distributor_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function saleDetails()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function reduceStock($quantity)
    {
        if ($this->stock >= $quantity) {
            $this->stock -= $quantity;
            $this->save();
            return true;
        }
        return false;
    }

    public function addStock($quantity)
    {
        $this->stock += $quantity;
        $this->save();
        return true;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($product) {
            $product->deletePhoto();
        });
    }
}

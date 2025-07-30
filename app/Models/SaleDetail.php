<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'selling_price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'selling_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * ✅ Relasi ke Sale
     */
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * ✅ Relasi ke Product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * ✅ Accessor untuk mendapatkan nama produk
     */
    public function getProductNameAttribute()
    {
        return $this->product ? $this->product->name : 'Produk tidak ditemukan';
    }

    /**
     * ✅ Accessor untuk mendapatkan kode produk
     */
    public function getProductCodeAttribute()
    {
        return $this->product ? $this->product->kode_barang : 'N/A';
    }

    /**
     * ✅ Accessor untuk format harga
     */
    public function getFormattedSellingPriceAttribute()
    {
        return 'Rp ' . number_format($this->selling_price, 0, ',', '.');
    }

    /**
     * ✅ Accessor untuk format subtotal
     */
    public function getFormattedSubtotalAttribute()
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }

    /**
     * ✅ Method untuk kalkulasi subtotal otomatis
     */
    public function calculateSubtotal()
    {
        $this->subtotal = $this->quantity * $this->selling_price;
        return $this;
    }

    /**
     * ✅ Scope untuk filter berdasarkan produk
     */
    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * ✅ Scope untuk filter berdasarkan sale
     */
    public function scopeBySale($query, $saleId)
    {
        return $query->where('sale_id', $saleId);
    }

    /**
     * ✅ Boot method untuk auto-calculate subtotal
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($saleDetail) {
            // Auto-calculate subtotal jika belum diset
            if ($saleDetail->quantity && $saleDetail->selling_price && !$saleDetail->subtotal) {
                $saleDetail->subtotal = $saleDetail->quantity * $saleDetail->selling_price;
            }
        });
    }
}

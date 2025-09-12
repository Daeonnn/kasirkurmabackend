<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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

    // Accessor
    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    // Photo management
    public function deletePhoto()
    {
        if ($this->photo && Storage::disk('public')->exists($this->photo)) {
            Storage::disk('public')->delete($this->photo);
        }
    }

    // Relationships
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

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class)->orderBy('created_at', 'desc');
    }

    // Stock Management Methods dengan tracking
    public function addStock($quantity, $distributorId = null, $notes = null)
    {
        return DB::transaction(function () use ($quantity, $distributorId, $notes) {
            // Update stock
            $this->stock += $quantity;
            $this->save();

            // Create movement record
            $movement = StockMovement::create([
                'product_id' => $this->id,
                'type' => 'in',
                'quantity' => $quantity,
                'distributor_id' => $distributorId,
                'notes' => $notes ?: 'Restock Baru',
                'user_id' => Auth::id()
            ]);

            return $movement;
        });
    }

    public function reduceStock($quantity, $notes = null)
    {
        return DB::transaction(function () use ($quantity, $notes) {
            if ($this->stock < $quantity) {
                throw new \Exception("Stok tidak cukup. Tersedia: {$this->stock}, Dibutuhkan: {$quantity}");
            }

            // Update stock
            $this->stock -= $quantity;
            $this->save();

            // Create movement record
            $movement = StockMovement::create([
                'product_id' => $this->id,
                'type' => 'out',
                'quantity' => $quantity,
                'distributor_id' => null, // Stock out tidak dari distributor
                'notes' => $notes ?: 'Pengurangan stok dari penjualan',
                'user_id' => Auth::id()
            ]);

            return $movement;
        });
    }

    // Legacy methods untuk backward compatibility
    public function addStockLegacy($quantity)
    {
        $this->stock += $quantity;
        $this->save();
        return true;
    }

    public function reduceStockLegacy($quantity)
    {
        if ($this->stock >= $quantity) {
            $this->stock -= $quantity;
            $this->save();
            return true;
        }
        return false;
    }

    // Boot method dengan protection
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($product) {
            // Check if product has sales history
            if ($product->saleDetails()->exists()) {
                throw new \Exception('Tidak dapat menghapus produk yang sudah pernah dijual');
            }

            // Check if product has stock movements
            if ($product->stockMovements()->exists()) {
                throw new \Exception('Tidak dapat menghapus produk yang memiliki riwayat stok');
            }

            // Delete photo
            $product->deletePhoto();
        });
    }
}

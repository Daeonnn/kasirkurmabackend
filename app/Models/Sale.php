<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_code',
        'date',
        'total_price',
        'payment_method',
        'cash_received',
        'change_amount',
        'user_id',
        'subtotal_amount',
        'discount_amount',
        'discount_type',
        'discount_value',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'date' => 'date',
        // ✅ DISCOUNT CASTS - BARU
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
    ];

    /**
     * ✅ Relasi ke SaleDetail
     */
    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    /**
     * ✅ Relasi ke User (Kasir yang melakukan transaksi)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ✅ Scope untuk filter berdasarkan tanggal
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * ✅ Scope untuk filter berdasarkan kasir
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * ✅ Scope untuk filter berdasarkan metode pembayaran
     */
    public function scopeByPaymentMethod($query, $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    /**
     * ✅ SCOPE BARU: Filter transaksi yang ada diskonnya
     */
    public function scopeWithDiscount($query)
    {
        return $query->whereNotNull('discount_amount')->where('discount_amount', '>', 0);
    }

    /**
     * ✅ SCOPE BARU: Filter berdasarkan tipe diskon
     */
    public function scopeByDiscountType($query, $discountType)
    {
        return $query->where('discount_type', $discountType);
    }

    /**
     * ✅ SCOPE BARU: Filter transaksi tanpa diskon
     */
    public function scopeWithoutDiscount($query)
    {
        return $query->where(function($q) {
            $q->whereNull('discount_amount')->orWhere('discount_amount', '<=', 0);
        });
    }

    /**
     * ✅ Accessor untuk format transaction code
     */
    public function getFormattedTransactionCodeAttribute()
    {
        return $this->transaction_code;
    }

    /**
     * ✅ Accessor untuk total items
     */
    public function getTotalItemsAttribute()
    {
        return $this->details->sum('quantity');
    }

    /**
     * ✅ Accessor untuk format tanggal Indonesia
     */
    public function getFormattedDateAttribute()
    {
        return $this->date ? $this->date->format('d/m/Y') : null;
    }

    /**
     * ✅ Accessor untuk status pembayaran
     */
    public function getPaymentStatusAttribute()
    {
        if ($this->payment_method === 'qris') {
            return 'QRIS';
        }

        if ($this->payment_method === 'tunai') {
            return 'TUNAI';
        }

        return 'TUNAI'; // default
    }

    /**
     * ✅ ACCESSOR BARU: Cek apakah transaksi memiliki diskon
     */
    public function getHasDiscountAttribute()
    {
        return $this->discount_amount && $this->discount_amount > 0;
    }

    /**
     * ✅ ACCESSOR BARU: Format display diskon
     */
    public function getFormattedDiscountAttribute()
    {
        if (!$this->has_discount) {
            return null;
        }

        $formatted = [
            'amount' => 'Rp ' . number_format($this->discount_amount, 0, ',', '.'),
            'type' => $this->discount_type,
            'value' => $this->discount_value
        ];

        if ($this->discount_type === 'percentage') {
            $formatted['display'] = "Diskon {$this->discount_value}% (Rp " . number_format($this->discount_amount, 0, ',', '.') . ")";
        } else {
            $formatted['display'] = "Diskon Rp " . number_format($this->discount_amount, 0, ',', '.');
        }

        return $formatted;
    }

    /**
     * ✅ ACCESSOR BARU: Format subtotal
     */
    public function getFormattedSubtotalAttribute()
    {
        return $this->subtotal_amount ? 'Rp ' . number_format($this->subtotal_amount, 0, ',', '.') : null;
    }

    /**
     * ✅ ACCESSOR BARU: Persentase diskon dari subtotal
     */
    public function getDiscountPercentageAttribute()
    {
        if (!$this->has_discount || !$this->subtotal_amount || $this->subtotal_amount <= 0) {
            return 0;
        }

        return round(($this->discount_amount / $this->subtotal_amount) * 100, 2);
    }

    /**
     * ✅ ACCESSOR BARU: Savings amount (berapa yang dihemat customer)
     */
    public function getSavingsAmountAttribute()
    {
        return $this->discount_amount ?? 0;
    }

    /**
     * ✅ Mutator untuk memastikan transaction_code format yang benar
     */
    public function setTransactionCodeAttribute($value)
    {
        // Pastikan format TR diikuti angka
        if (!preg_match('/^TR\d+$/', $value)) {
            throw new \InvalidArgumentException('Transaction code harus dalam format TR diikuti angka');
        }

        $this->attributes['transaction_code'] = $value;
    }

    /**
     * ✅ MUTATOR BARU: Validasi discount_amount
     */
    public function setDiscountAmountAttribute($value)
    {
        if ($value !== null && $value < 0) {
            throw new \InvalidArgumentException('Discount amount tidak boleh negatif');
        }
        $this->attributes['discount_amount'] = $value;
    }

    /**
     * ✅ MUTATOR BARU: Validasi discount_type
     */
    public function setDiscountTypeAttribute($value)
    {
        if ($value && !in_array($value, ['percentage', 'fixed'])) {
            throw new \InvalidArgumentException('Discount type harus percentage atau fixed');
        }
        $this->attributes['discount_type'] = $value;
    }

    /**
     * ✅ MUTATOR BARU: Validasi discount_value
     */
    public function setDiscountValueAttribute($value)
    {
        if ($value !== null && $value < 0) {
            throw new \InvalidArgumentException('Discount value tidak boleh negatif');
        }
        $this->attributes['discount_value'] = $value;
    }

    /**
     * ✅ METHOD BARU: Kalkulasi total dengan diskon
     */
    public function calculateTotalWithDiscount($subtotal, $discountType, $discountValue)
    {
        $discountAmount = 0;

        if ($discountType === 'percentage') {
            if ($discountValue > 100) {
                throw new \InvalidArgumentException('Persentase diskon tidak boleh lebih dari 100%');
            }
            $discountAmount = ($subtotal * $discountValue) / 100;
        } elseif ($discountType === 'fixed') {
            if ($discountValue > $subtotal) {
                throw new \InvalidArgumentException('Nominal diskon tidak boleh lebih besar dari subtotal');
            }
            $discountAmount = $discountValue;
        }

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'total' => $subtotal - $discountAmount
        ];
    }

    /**
     * ✅ METHOD BARU: Validasi data diskon
     */
    public function validateDiscount($discountType, $discountValue, $subtotal)
    {
        if (!$discountType || !$discountValue) {
            return ['valid' => true, 'message' => 'No discount'];
        }

        if (!in_array($discountType, ['percentage', 'fixed'])) {
            return ['valid' => false, 'message' => 'Tipe diskon tidak valid'];
        }

        if ($discountValue <= 0) {
            return ['valid' => false, 'message' => 'Nilai diskon harus lebih dari 0'];
        }

        if ($discountType === 'percentage' && $discountValue > 100) {
            return ['valid' => false, 'message' => 'Persentase diskon tidak boleh lebih dari 100%'];
        }

        if ($discountType === 'fixed' && $discountValue > $subtotal) {
            return ['valid' => false, 'message' => 'Nominal diskon tidak boleh lebih besar dari subtotal'];
        }

        return ['valid' => true, 'message' => 'Discount valid'];
    }

    /**
     * ✅ Method untuk mendapatkan transaction code berikutnya
     */
    public static function getNextTransactionCode()
    {
        $lastSale = static::where('transaction_code', 'LIKE', 'TR%')
            ->orderByRaw('CAST(SUBSTRING(transaction_code, 3) AS UNSIGNED) DESC')
            ->first();

        if (!$lastSale) {
            return 'TR001';
        }

        if (preg_match('/^TR(\d+)$/', $lastSale->transaction_code, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
            return 'TR' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        }

        return 'TR001';
    }

    /**
     * ✅ Method untuk validasi transaction code unik
     */
    public static function isTransactionCodeExists($transactionCode)
    {
        return static::where('transaction_code', $transactionCode)->exists();
    }

    /**
     * ✅ METHOD BARU: Statistik diskon
     */
    public static function getDiscountStatistics($startDate = null, $endDate = null)
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        $allSales = $query->get();
        $salesWithDiscount = $allSales->where('discount_amount', '>', 0);

        return [
            'total_sales' => $allSales->count(),
            'sales_with_discount' => $salesWithDiscount->count(),
            'sales_without_discount' => $allSales->where('discount_amount', '<=', 0)->count(),
            'percentage_with_discount' => $allSales->count() > 0 ? round(($salesWithDiscount->count() / $allSales->count()) * 100, 2) : 0,
            'total_discount_amount' => $salesWithDiscount->sum('discount_amount'),
            'total_original_amount' => $salesWithDiscount->sum('subtotal_amount'),
            'total_final_amount' => $allSales->sum('total_price'),
            'average_discount_amount' => $salesWithDiscount->count() > 0 ? round($salesWithDiscount->avg('discount_amount'), 2) : 0,
            'highest_discount' => $salesWithDiscount->max('discount_amount') ?? 0,
            'most_common_discount_type' => $salesWithDiscount->groupBy('discount_type')->map->count()->sort()->keys()->last(),
        ];
    }

    /**
     * ✅ METHOD BARU: Laporan diskon detail
     */
    public static function getDiscountReport($startDate = null, $endDate = null)
    {
        $query = static::withDiscount()
            ->with(['details.product', 'user']);

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }

        $sales = $query->orderBy('date', 'desc')->get();
        $statistics = static::getDiscountStatistics($startDate, $endDate);

        return [
            'statistics' => $statistics,
            'sales' => $sales,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ];
    }

    /**
     * ✅ Method untuk mendapatkan laporan harian
     */
    public static function getDailyReport($date = null)
    {
        $date = $date ?: now()->format('Y-m-d');

        return static::with(['details.product', 'user'])
            ->whereDate('date', $date)
            ->get();
    }

    /**
     * ✅ Method untuk mendapatkan laporan bulanan
     */
    public static function getMonthlyReport($year = null, $month = null)
    {
        $year = $year ?: now()->year;
        $month = $month ?: now()->month;

        return static::with(['details.product', 'user'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();
    }

    /**
     * ✅ Boot method untuk auto-generate transaction code dan validasi diskon
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            // Jika transaction_code kosong, generate otomatis
            if (empty($sale->transaction_code)) {
                $sale->transaction_code = static::getNextTransactionCode();
            }

            // ✅ VALIDASI DISKON SAAT CREATING
            if ($sale->discount_amount && $sale->discount_amount > 0) {
                if (!$sale->discount_type) {
                    throw new \InvalidArgumentException('Discount type harus diisi jika ada discount amount');
                }

                if (!$sale->subtotal_amount) {
                    throw new \InvalidArgumentException('Subtotal amount harus diisi jika ada discount');
                }

                if ($sale->discount_amount > $sale->subtotal_amount) {
                    throw new \InvalidArgumentException('Discount amount tidak boleh lebih besar dari subtotal');
                }

                // Validasi konsistensi kalkulasi diskon
                $expectedDiscount = 0;
                if ($sale->discount_type === 'percentage') {
                    $expectedDiscount = ($sale->subtotal_amount * $sale->discount_value) / 100;
                } elseif ($sale->discount_type === 'fixed') {
                    $expectedDiscount = $sale->discount_value;
                }

                if (abs($expectedDiscount - $sale->discount_amount) > 0.01) {
                    throw new \InvalidArgumentException('Kalkulasi diskon tidak konsisten');
                }
            }
        });

        static::updating(function ($sale) {
            // ✅ VALIDASI DISKON SAAT UPDATING
            if ($sale->discount_amount && $sale->discount_amount > 0) {
                if (!$sale->discount_type) {
                    throw new \InvalidArgumentException('Discount type harus diisi jika ada discount amount');
                }

                if (!$sale->subtotal_amount) {
                    throw new \InvalidArgumentException('Subtotal amount harus diisi jika ada discount');
                }

                if ($sale->discount_amount > $sale->subtotal_amount) {
                    throw new \InvalidArgumentException('Discount amount tidak boleh lebih besar dari subtotal');
                }
            }
        });
    }
}

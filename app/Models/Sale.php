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
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
    ];

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByPaymentMethod($query, $paymentMethod)
    {
        return $query->where('payment_method', $paymentMethod);
    }

    public function scopeWithDiscount($query)
    {
        return $query->whereNotNull('discount_amount')->where('discount_amount', '>', 0);
    }

    public function scopeByDiscountType($query, $discountType)
    {
        return $query->where('discount_type', $discountType);
    }

    public function scopeWithoutDiscount($query)
    {
        return $query->where(function($q) {
            $q->whereNull('discount_amount')->orWhere('discount_amount', '<=', 0);
        });
    }

    public function getFormattedTransactionCodeAttribute()
    {
        return $this->transaction_code;
    }

    public function getTotalItemsAttribute()
    {
        return $this->details->sum('quantity');
    }

    public function getFormattedDateAttribute()
    {
        return $this->date ? $this->date->format('d/m/Y') : null;
    }

    public function getPaymentStatusAttribute()
    {
        if ($this->payment_method === 'qris') {
            return 'QRIS';
        }

        if ($this->payment_method === 'tunai') {
            return 'TUNAI';
        }

        return 'TUNAI';
    }

    public function getHasDiscountAttribute()
    {
        return $this->discount_amount && $this->discount_amount > 0;
    }

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

    public function getFormattedSubtotalAttribute()
    {
        return $this->subtotal_amount ? 'Rp ' . number_format($this->subtotal_amount, 0, ',', '.') : null;
    }

    public function getDiscountPercentageAttribute()
    {
        if (!$this->has_discount || !$this->subtotal_amount || $this->subtotal_amount <= 0) {
            return 0;
        }

        return round(($this->discount_amount / $this->subtotal_amount) * 100, 2);
    }

    public function getSavingsAmountAttribute()
    {
        return $this->discount_amount ?? 0;
    }

    public function setTransactionCodeAttribute($value)
    {
        if (!preg_match('/^TR\d{6}\d{6}$/', $value)) {
            throw new \InvalidArgumentException('Transaction code harus dalam format TR + 6 digit tanggal (YYMMDD) + 6 digit nomor urut');
        }

        $this->attributes['transaction_code'] = $value;
    }

    public function setDiscountAmountAttribute($value)
    {
        if ($value !== null && $value < 0) {
            throw new \InvalidArgumentException('Discount amount tidak boleh negatif');
        }
        $this->attributes['discount_amount'] = $value;
    }

    public function setDiscountTypeAttribute($value)
    {
        if ($value && !in_array($value, ['percentage', 'fixed'])) {
            throw new \InvalidArgumentException('Discount type harus percentage atau fixed');
        }
        $this->attributes['discount_type'] = $value;
    }

    public function setDiscountValueAttribute($value)
    {
        if ($value !== null && $value < 0) {
            throw new \InvalidArgumentException('Discount value tidak boleh negatif');
        }
        $this->attributes['discount_value'] = $value;
    }

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

    public static function getNextTransactionCode()
    {
        // Format: TR + YY + MM + DD + 000001
        $today = now();
        $datePrefix = $today->format('ymd'); // 250829 untuk 29 Agustus 2025
        $transactionPrefix = 'TR' . $datePrefix;
        
        // Ambil transaksi terakhir untuk hari ini
        $lastSale = static::where('transaction_code', 'LIKE', $transactionPrefix . '%')
            ->orderByRaw('CAST(SUBSTRING(transaction_code, 9) AS UNSIGNED) DESC')
            ->first();

        $nextNumber = 1;

        // Jika ada transaksi hari ini, ambil nomor urut terakhir dan tambah 1
        if ($lastSale && preg_match('/^TR\d{6}(\d{6})$/', $lastSale->transaction_code, $matches)) {
            $nextNumber = (int) $matches[1] + 1;
        }

        return $transactionPrefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    public static function isTransactionCodeExists($transactionCode)
    {
        return static::where('transaction_code', $transactionCode)->exists();
    }

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

    public static function getDiscountReport($startDate = null, $endDate = null)
    {
        $query = static::withDiscount()->with(['details.product', 'user']);

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

    public static function getDailyReport($date = null)
    {
        $date = $date ?: now()->format('Y-m-d');

        return static::with(['details.product', 'user'])
            ->whereDate('date', $date)
            ->get();
    }

    public static function getMonthlyReport($year = null, $month = null)
    {
        $year = $year ?: now()->year;
        $month = $month ?: now()->month;

        return static::with(['details.product', 'user'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->transaction_code)) {
                $sale->transaction_code = static::getNextTransactionCode();
            }

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

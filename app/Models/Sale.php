<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_code',
        'transaction_sequence', // ✅ TAMBAHAN BARU untuk per-user system
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
        'transaction_sequence' => 'integer', // ✅ TAMBAHAN BARU
        'total_price' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'date' => 'date',
        'subtotal_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_value' => 'decimal:2',
    ];

    // ✅ RELATIONSHIPS (tidak berubah)
    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // ✅ SCOPES (tambahkan scope baru untuk per-user)
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

    // ✅ ACCESSORS (tidak berubah, tetap sama)
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

    // ✅ MUTATORS - Update untuk per-user system
    public function setTransactionCodeAttribute($value)
    {
       if (!preg_match('/^TR\d{8}\d{6}$/', $value)) {
    throw new \InvalidArgumentException('Transaction code harus dalam format TR-YYYY-MM-DD-XXX (contoh: TR-2025-08-30-001)');
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

    // ✅ BUSINESS LOGIC METHODS (tidak berubah)
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

    // ✅ UPDATED: Per-User Transaction Code Methods
   public static function getNextTransactionCode($userId = null)
{
    if (!$userId) {
        throw new \InvalidArgumentException('User ID diperlukan untuk generate transaction code');
    }

    $today = now()->format('Ymd'); // Format tanggal: YYYY-MM-DD

    // Gunakan transaksi database agar aman dari race condition
    $result = DB::transaction(function () use ($userId, $today) {
        // 🔒 Lock row untuk user ini pada hari ini, urutkan dari sequence terbesar
        $latestSale = static::lockForUpdate()
            ->where('user_id', $userId)
            ->where('date', $today)
            ->orderBy('transaction_sequence', 'desc')
            ->first();

        $nextSequence = $latestSale ? $latestSale->transaction_sequence + 1 : 1;

        // Format: TR-YYYY-MM-DD-XXX (3 digit sequence)
        $transactionCode = 'TR' . $today . str_pad($nextSequence, 6, '0', STR_PAD_LEFT);

        return [
            'transaction_code' => $transactionCode,
            'transaction_sequence' => $nextSequence,
        ];
    });

    return $result;
}

    // ✅ UPDATED: Check per-user transaction code
    public static function isTransactionCodeExists($transactionCode, $userId = null)
    {
        if (!$userId) {
            throw new \InvalidArgumentException('User ID diperlukan untuk check transaction code');
        }

        return static::where('transaction_code', $transactionCode)
            ->where('user_id', $userId)
            ->exists();
    }

    // ✅ UPDATED: Check per-user transaction sequence
    public static function isTransactionSequenceExists($sequence, $userId = null)
    {
        if (!$userId) {
            // Dalam static context, kita perlu require userId
            throw new \InvalidArgumentException('User ID diperlukan untuk check transaction sequence');
        }

        return static::where('transaction_sequence', $sequence)
            ->where('user_id', $userId)
            ->exists();
    }

    // ✅ UPDATED: Discount statistics dengan filter user
    public static function getDiscountStatistics($startDate = null, $endDate = null, $userId = null)
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        if ($userId) {
            $query->where('user_id', $userId);
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

    // ✅ UPDATED: Discount report dengan filter user
    public static function getDiscountReport($startDate = null, $endDate = null, $userId = null)
    {
        $query = static::withDiscount()->with(['details.product', 'user']);

        if ($startDate && $endDate) {
            $query->whereBetween('date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $sales = $query->orderBy('date', 'desc')->get();
        $statistics = static::getDiscountStatistics($startDate, $endDate, $userId);

        return [
            'statistics' => $statistics,
            'sales' => $sales,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'user_id' => $userId
            ]
        ];
    }

    // ✅ UPDATED: Daily report dengan filter user
    public static function getDailyReport($date = null, $userId = null)
    {
        $date = $date ?: now()->format('Ymd');
        $query = static::with(['details.product', 'user'])
            ->whereDate('date', $date);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    // ✅ UPDATED: Monthly report dengan filter user
    public static function getMonthlyReport($year = null, $month = null, $userId = null)
    {
        $year = $year ?: now()->year;
        $month = $month ?: now()->month;

        $query = static::with(['details.product', 'user'])
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    // ✅ UPDATED: Boot method untuk per-user system
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            // ✅ Auto-generate transaction code dan sequence jika belum ada
            if (empty($sale->transaction_code) || empty($sale->transaction_sequence)) {
                $nextTransaction = static::getNextTransactionCode($sale->user_id);
                $sale->transaction_code = $nextTransaction['transaction_code'];
                $sale->transaction_sequence = $nextTransaction['transaction_sequence'];
            }

            // ✅ Validasi discount (tidak berubah)
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
            // ✅ Validasi discount saat update (tidak berubah)
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

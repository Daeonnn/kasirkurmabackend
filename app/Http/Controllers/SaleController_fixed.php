<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    /**
     * ✅ FIXED: Get next transaction code dengan format TR + YYMMDD + 6-digit sequence (reset harian per user)
     * Format: TR250830000001 (30 Agustus 2025, transaksi ke-1 untuk user ini)
     */
    public function getNextTransactionCode()
    {
        try {
            return DB::transaction(function () {
                $userId = Auth::id();

                if (!$userId) {
                    throw new \Exception('User tidak terautentikasi');
                }

                // ✅ FIXED: Format kode sesuai permintaan TRYYMMDD000001
                $year = now()->format('y');     // 2 digit tahun (25 untuk 2025)
                $month = now()->format('m');    // 2 digit bulan (08 untuk Agustus)
                $day = now()->format('d');      // 2 digit hari (29 untuk tanggal 29)

                // Gabungkan untuk membuat format TRYYMMDD
                $dateCode = $year . $month . $day; // 250829

                Log::info('🔄 Generating transaction code', [
                    'user_id' => $userId,
                    'date_code' => $dateCode,
                    'full_date' => now()->toDateString()
                ]);

                // ✅ FIXED: Ambil transaksi terakhir untuk USER ini pada HARI ini dengan format baru
                $datePrefix = 'TR' . $dateCode;
                $latestSale = Sale::lockForUpdate()
                    ->where('user_id', $userId)
                    ->where('transaction_code', 'LIKE', $datePrefix . '%')
                    ->whereDate('date', now()->toDateString())
                    ->orderByRaw('CAST(SUBSTRING(transaction_code, 9) AS UNSIGNED) DESC')
                    ->first();

                // ✅ FIXED: Reset sequence setiap hari untuk setiap user
                $nextSequence = 1;
                
                if ($latestSale) {
                    // Extract the sequence number from the last transaction code
                    if (preg_match('/^TR\d{6}(\d{6})$/', $latestSale->transaction_code, $matches)) {
                        $nextSequence = (int) $matches[1] + 1;
                    }
                }

                // ✅ FIXED: Format kode transaksi dengan 6 digit sequence (000001)
                $nextCode = $datePrefix . str_pad($nextSequence, 6, '0', STR_PAD_LEFT);

                // ✅ FIXED: Pastikan kode transaksi unik untuk user ini
                $attempts = 0;
                $maxAttempts = 10; // Batasi percobaan untuk menghindari infinite loop

                do {
                    $exists = Sale::where('user_id', $userId)
                        ->where('transaction_code', $nextCode)
                        ->exists();

                    if ($exists) {
                        $nextSequence++;
                        $nextCode = $datePrefix . str_pad($nextSequence, 6, '0', STR_PAD_LEFT);
                        $attempts++;

                        if ($attempts >= $maxAttempts) {
                            throw new \Exception('Tidak dapat generate kode transaksi unik setelah ' . $maxAttempts . ' percobaan');
                        }
                    }
                } while ($exists);

                Log::info('✅ Next transaction code generated', [
                    'next_code' => $nextCode,
                    'next_sequence' => $nextSequence,
                    'user_id' => $userId,
                    'date_code' => $dateCode,
                    'attempts' => $attempts
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'next_code' => $nextCode,
                        'next_sequence' => $nextSequence,
                        'user_id' => $userId
                    ]
                ]);
            });

        } catch (\Exception $e) {
            Log::error('❌ Error generating next transaction code', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan kode transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $userId = Auth::id();

        Log::info('🚀 Sale transaction started for user', [
            'transaction_code' => $request->transaction_code,
            'user_id' => $userId,
            'items_count' => count($request->items ?? []),
            'has_discount' => $request->has('discount') && $request->discount,
            'total_amount' => $request->total_amount
        ]);

        // ✅ VALIDATION UPDATED - per-user transaction code + sequence
        $validator = Validator::make($request->all(), [
            'transaction_code' => [
                'required',
                'string',
                'regex:/^TR\d{6}\d{6}$/',
                function ($attribute, $value, $fail) use ($userId) {
                    if (Sale::isTransactionCodeExists($value, $userId)) {
                        $fail('Transaction code sudah digunakan untuk user ini.');
                    }
                }
            ],
            'transaction_sequence' => 'required|integer|min:1',
            'date' => 'required|date',
            'payment_method' => 'required|string|in:tunai,qris',
            'cash_received' => 'required|numeric|min:0',
            'change_amount' => 'required|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.selling_price' => 'required|numeric|min:0',
            'items.*.subtotal' => 'required|numeric|min:0',
            // ✅ VALIDASI DISKON
            'discount' => 'nullable|array',
            'discount.type' => 'nullable|required_with:discount|in:percentage,fixed',
            'discount.value' => 'nullable|required_with:discount|numeric|min:0',
            'discount.amount' => 'nullable|required_with:discount|numeric|min:0',
        ]);

        // ✅ CUSTOM VALIDATION untuk diskon dan sequence
        $validator->after(function ($validator) use ($request, $userId) {
            // Validasi sequence untuk user ini
            $expectedSequence = Sale::where('user_id', $userId)->max('transaction_sequence');
            $expectedSequence = $expectedSequence ? $expectedSequence + 1 : 1;

            if ($request->transaction_sequence != $expectedSequence) {
                $validator->errors()->add('transaction_sequence', 'Transaction sequence tidak sesuai. Expected: ' . $expectedSequence);
            }

            // Validasi diskon
            if ($request->has('discount') && $request->discount) {
                $discount = $request->discount;

                if ($discount['type'] === 'percentage' && $discount['value'] > 100) {
                    $validator->errors()->add('discount.value', 'Persentase diskon tidak boleh lebih dari 100%');
                }

                $calculatedSubtotal = collect($request->items)->sum('subtotal');

                if ($discount['type'] === 'fixed' && $discount['value'] > $calculatedSubtotal) {
                    $validator->errors()->add('discount.value', 'Nominal diskon tidak boleh lebih besar dari subtotal');
                }

                $expectedDiscount = 0;
                if ($discount['type'] === 'percentage') {
                    $expectedDiscount = ($calculatedSubtotal * $discount['value']) / 100;
                } elseif ($discount['type'] === 'fixed') {
                    $expectedDiscount = $discount['value'];
                }

                if (abs($expectedDiscount - $discount['amount']) > 0.01) {
                    $validator->errors()->add('discount.amount', 'Kalkulasi diskon tidak sesuai');
                }
            }
        });

        if ($validator->fails()) {
            Log::warning('❌ Validation failed', [
                'errors' => $validator->errors(),
                'transaction_code' => $request->transaction_code,
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        // ✅ PROCESS TRANSACTION DENGAN STOCK MOVEMENT INTEGRATION
        return DB::transaction(function () use ($request, $userId) {
            $transactionCode = $request->transaction_code;
            $transactionSequence = $request->transaction_sequence;
            $subtotalCalculated = 0;
            $details = [];

            // ✅ VALIDASI & UPDATE STOCK DENGAN STOCK MOVEMENT
            foreach ($request->items as $item) {
                $product = Product::lockForUpdate()->find($item['product_id']);

                if (!$product) {
                    throw new \Exception('Produk dengan ID ' . $item['product_id'] . ' tidak ditemukan');
                }

                // ✅ CEK STOCK
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Stok tidak cukup untuk produk: {$product->name}. Stok tersedia: {$product->stock}, diminta: {$item['quantity']}");
                }

                // ✅ VALIDASI HARGA
                $expectedSubtotal = $item['quantity'] * $item['selling_price'];
                if (abs($expectedSubtotal - $item['subtotal']) > 0.01) {
                    throw new \Exception("Subtotal tidak sesuai untuk produk: {$product->name}");
                }

                $subtotalCalculated += $item['subtotal'];

                $details[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'selling_price' => $item['selling_price'],
                    'subtotal' => $item['subtotal'],
                ];

                if (method_exists($product, 'reduceStock')) {
                    $product->reduceStock(
                        $item['quantity'],
                        "Penjualan"
                    );
                } else {
                    // Fallback jika method belum ada
                    $product->stock -= $item['quantity'];
                    $product->save();
                }

                Log::info('📦 Stock reduced with tracking', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_sold' => $item['quantity'],
                    'stock_remaining' => $product->fresh()->stock,
                    'transaction_code' => $transactionCode,
                    'transaction_sequence' => $transactionSequence,
                    'sold_by_kasir' => $userId
                ]);
            }

            // ✅ VALIDASI DAN KALKULASI DISKON
            $discountAmount = 0;
            $discountType = null;
            $discountValue = null;
            $finalTotal = $subtotalCalculated;

            if ($request->has('discount') && $request->discount) {
                $discount = $request->discount;
                $discountType = $discount['type'];
                $discountValue = $discount['value'];
                $expectedDiscountAmount = $discount['amount'];

                // ✅ VALIDASI DISKON
                if ($discountType === 'percentage') {
                    if ($discountValue > 100) {
                        throw new \Exception('Persentase diskon tidak boleh lebih dari 100%');
                    }
                    $calculatedDiscountAmount = ($subtotalCalculated * $discountValue) / 100;
                } elseif ($discountType === 'fixed') {
                    if ($discountValue > $subtotalCalculated) {
                        throw new \Exception('Nominal diskon tidak boleh lebih besar dari subtotal');
                    }
                    $calculatedDiscountAmount = $discountValue;
                } else {
                    throw new \Exception('Tipe diskon tidak valid');
                }

                // ✅ VALIDASI KALKULASI DISKON
                if (abs($calculatedDiscountAmount - $expectedDiscountAmount) > 0.01) {
                    throw new \Exception('Kalkulasi diskon tidak sesuai');
                }

                $discountAmount = $calculatedDiscountAmount;
                $finalTotal = $subtotalCalculated - $discountAmount;

                Log::info('💰 Discount applied for user', [
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount_amount' => $discountAmount,
                    'subtotal' => $subtotalCalculated,
                    'final_total' => $finalTotal,
                    'user_id' => $userId
                ]);
            }

            // ✅ VALIDASI TOTAL FINAL
            if (abs($finalTotal - $request->total_amount) > 0.01) {
                throw new \Exception('Total amount tidak sesuai dengan perhitungan setelah diskon');
            }

            // ✅ VALIDASI PEMBAYARAN
            if ($request->payment_method === 'tunai') {
                if ($request->cash_received < $request->total_amount) {
                    throw new \Exception('Uang yang diterima kurang dari total belanja');
                }

                $expectedChange = $request->cash_received - $request->total_amount;
                if (abs($expectedChange - $request->change_amount) > 0.01) {
                    throw new \Exception('Kembalian tidak sesuai');
                }
            }

            // ✅ CREATE SALE RECORD DENGAN SISTEM PER-USER
            $saleData = [
                'transaction_code' => $transactionCode,
                'transaction_sequence' => $transactionSequence,
                'date' => $request->date,
                'subtotal_amount' => $subtotalCalculated,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => $discountAmount,
                'total_price' => $request->total_amount,
                'payment_method' => $request->payment_method,
                'cash_received' => $request->cash_received,
                'change_amount' => $request->change_amount,
                'user_id' => $userId,
            ];

            $sale = Sale::create($saleData);

            // ✅ CREATE SALE DETAILS
            foreach ($details as $detail) {
                $detail['sale_id'] = $sale->id;
                SaleDetail::create($detail);
            }

            Log::info('✅ Transaction completed successfully with per-user system', [
                'sale_id' => $sale->id,
                'transaction_code' => $sale->transaction_code,
                'transaction_sequence' => $sale->transaction_sequence,
                'subtotal_price' => $sale->subtotal_amount,
                'discount_amount' => $sale->discount_amount,
                'total_price' => $sale->total_price,
                'payment_method' => $sale->payment_method,
                'kasir_id' => $userId
            ]);

            // ✅ RETURN RESPONSE DENGAN DATA LENGKAP
            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan',
                'data' => [
                    'id' => $sale->id,
                    'transaction_code' => $sale->transaction_code,
                    'transaction_sequence' => $sale->transaction_sequence,
                    'date' => $sale->date,
                    'subtotal_amount' => $sale->subtotal_amount,
                    'discount_type' => $sale->discount_type,
                    'discount_value' => $sale->discount_value,
                    'discount_amount' => $sale->discount_amount,
                    'total_price' => $sale->total_price,
                    'payment_method' => $sale->payment_method,
                    'cash_received' => $sale->cash_received,
                    'change_amount' => $sale->change_amount,
                    'user_id' => $sale->user_id,
                    'has_discount' => $sale->has_discount,
                    'formatted_discount' => $sale->formatted_discount,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->updated_at,
                ]
            ], 201);
        });
    }

    /**
     * ✅ UPDATED: GET ALL SALES - dengan filter per role (kasir hanya lihat transaksi sendiri)
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Sale::with(['details.product', 'user']);

            // ✅ FILTER PER ROLE
            if ($user && $user->role && $user->role->name === 'kasir') {
                // Kasir hanya bisa lihat transaksi mereka sendiri
                $query->where('user_id', $user->id);
            }
            // Admin bisa lihat semua transaksi

            // ✅ OPTIONAL FILTER BY USER (untuk admin)
            if ($request->has('user_id') && $user && $user->role && $user->role->name === 'admin') {
                $query->where('user_id', $request->user_id);
            }

            $sales = $query->orderBy('transaction_sequence', 'desc')
                ->orderBy('id', 'desc')
                ->get();

            // ✅ TAMBAHKAN INFO DISKON KE RESPONSE
            $salesWithDiscountInfo = $sales->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'transaction_code' => $sale->transaction_code,
                    'transaction_sequence' => $sale->transaction_sequence,
                    'date' => $sale->date,
                    'subtotal_amount' => $sale->subtotal_amount,
                    'discount_type' => $sale->discount_type,
                    'discount_value' => $sale->discount_value,
                    'discount_amount' => $sale->discount_amount,
                    'total_price' => $sale->total_price,
                    'payment_method' => $sale->payment_method,
                    'cash_received' => $sale->cash_received,
                    'change_amount' => $sale->change_amount,
                    'user_id' => $sale->user_id,
                    'has_discount' => $sale->has_discount,
                    'formatted_discount' => $sale->formatted_discount,
                    'formatted_subtotal' => $sale->formatted_subtotal,
                    'discount_percentage' => $sale->discount_percentage,
                    'savings_amount' => $sale->savings_amount,
                    'user' => $sale->user,
                    'details' => $sale->details,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Data transaksi berhasil diambil',
                'data' => $salesWithDiscountInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching sales', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data transaksi'
            ], 500);
        }
    }

    /**
     * ✅ UPDATED: GET SALE DETAIL - dengan role-based access
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            $query = Sale::with(['details.product', 'user']);

            // ✅ FILTER PER ROLE
            if ($user && $user->role && $user->role->name === 'kasir') {
                $query->where('user_id', $user->id);
            }

            $sale = $query->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan atau tidak memiliki akses'
                ], 404);
            }

            // ✅ TAMBAHKAN INFO DISKON KE RESPONSE
            $saleWithDiscountInfo = [
                'id' => $sale->id,
                'transaction_code' => $sale->transaction_code,
                'transaction_sequence' => $sale->transaction_sequence,
                'date' => $sale->date,
                'subtotal_amount' => $sale->subtotal_amount,
                'discount_type' => $sale->discount_type,
                'discount_value' => $sale->discount_value,
                'discount_amount' => $sale->discount_amount,
                'total_price' => $sale->total_price,
                'payment_method' => $sale->payment_method,
                'cash_received' => $sale->cash_received,
                'change_amount' => $sale->change_amount,
                'user_id' => $sale->user_id,
                'has_discount' => $sale->has_discount,
                'formatted_discount' => $sale->formatted_discount,
                'formatted_subtotal' => $sale->formatted_subtotal,
                'discount_percentage' => $sale->discount_percentage,
                'savings_amount' => $sale->savings_amount,
                'user' => $sale->user,
                'details' => $sale->details,
                'created_at' => $sale->created_at,
                'updated_at' => $sale->updated_at,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Detail transaksi berhasil diambil',
                'data' => $saleWithDiscountInfo
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching sale detail', [
                'sale_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail transaksi'
            ], 500);
        }
    }

    /**
     * ✅ UPDATED: SALES REPORT DENGAN STATISTIK PER-USER - Admin only
     */
    public function report(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->role || $user->role->name !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat mengakses laporan.'
                ], 403);
            }

            $query = Sale::with(['details.product', 'user']);

            // ✅ FILTER OPTIONS
            if ($request->has('start_date')) {
                $query->whereDate('date', '>=', $request->start_date);
            }
            if ($request->has('end_date')) {
                $query->whereDate('date', '<=', $request->end_date);
            }
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $sales = $query->orderBy('id', 'desc')->get();

            $totalRevenue = $sales->sum('total_price');
            $totalTransactions = $sales->count();
            $totalItemsSold = $sales->flatMap->details->sum('quantity');

            // ✅ STATISTIK DISKON
            $discountStatistics = Sale::getDiscountStatistics(
                $request->start_date,
                $request->end_date,
                $request->user_id
            );

            // ✅ STATISTIK PER USER
            $userStatistics = $sales->groupBy('user_id')->map(function ($userSales, $userId) {
                $user = $userSales->first()->user;
                return [
                    'user_id' => $userId,
                    'user_name' => $user ? $user->name : 'Unknown',
                    'total_transactions' => $userSales->count(),
                    'total_revenue' => $userSales->sum('total_price'),
                    'total_discount' => $userSales->sum('discount_amount'),
                    'max_sequence' => $userSales->max('transaction_sequence'),
                ];
            });

            Log::info('📊 Sales report with per-user system generated by admin', [
                'total_transactions' => $totalTransactions,
                'total_revenue' => $totalRevenue,
                'total_discount_amount' => $discountStatistics['total_discount_amount'],
                'users_count' => $userStatistics->count(),
                'admin_user_id' => Auth::id(),
                'date_range' => [
                    'start' => $request->start_date,
                    'end' => $request->end_date
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Laporan berhasil diambil',
                'data' => [
                    'total_revenue' => $totalRevenue,
                    'total_transactions' => $totalTransactions,
                    'total_items_sold' => $totalItemsSold,
                    'discount_statistics' => $discountStatistics,
                    'user_statistics' => $userStatistics,
                    'sales' => $sales
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating sales report', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan'
            ], 500);
        }
    }

    /**
     * ✅ ENDPOINT: Laporan khusus diskon - Admin only
     */
    public function discountReport(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->role || $user->role->name !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat mengakses laporan diskon.'
                ], 403);
            }

            $discountReport = Sale::getDiscountReport(
                $request->start_date,
                $request->end_date,
                $request->user_id
            );

            Log::info('📊 Discount report generated by admin', [
                'total_sales_with_discount' => $discountReport['statistics']['sales_with_discount'],
                'total_discount_amount' => $discountReport['statistics']['total_discount_amount'],
                'admin_user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Laporan diskon berhasil diambil',
                'data' => $discountReport
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating discount report', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan diskon'
            ], 500);
        }
    }

    /**
     * ✅ UPDATED: Statistik diskon untuk dashboard - per role
     */
    public function discountStats(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->role || !in_array($user->role->name, ['admin', 'kasir'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak.'
                ], 403);
            }

            $endDate = $request->end_date ?: now()->format('Y-m-d');
            $startDate = $request->start_date ?: now()->subDays(30)->format('Y-m-d');

            // ✅ FILTER BERDASARKAN ROLE
            $userId = null;
            if ($user->role->name === 'kasir') {
                $userId = $user->id; // Kasir hanya lihat statistik mereka sendiri
            } elseif ($request->has('user_id')) {
                $userId = $request->user_id; // Admin bisa filter by user
            }

            $stats = Sale::getDiscountStatistics($startDate, $endDate, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Statistik diskon berhasil diambil',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching discount stats', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik diskon'
            ], 500);
        }
    }

    /**
     * ✅ UPDATE SALE - Tetap disabled untuk integritas data
     */
    public function update(Request $request, $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Update transaksi tidak diizinkan untuk menjaga integritas data'
        ], 403);
    }

    /**
     * ✅ DELETE SALE DENGAN RESTORE STOCK - Admin only
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->role || $user->role->name !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat menghapus transaksi.'
                ], 403);
            }

            return DB::transaction(function () use ($id) {
                $sale = Sale::with('details.product')->find($id);

                if (!$sale) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Transaksi tidak ditemukan'
                    ], 404);
                }

                // ✅ RESTORE STOCK saat hapus transaksi
                foreach ($sale->details as $detail) {
                    $product = $detail->product;
                    if ($product) {
                        if (method_exists($product, 'addStock')) {
                            $product->addStock(
                                $detail->quantity,
                                null,
                                "Restore stok dari pembatalan transaksi {$sale->transaction_code} (User: {$sale->user_id})"
                            );
                        } else {
                            // Fallback
                            $product->stock += $detail->quantity;
                            $product->save();
                        }

                        Log::info('📦 Stock restored after transaction deletion', [
                            'product_id' => $product->id,
                            'quantity_restored' => $detail->quantity,
                            'new_stock' => $product->fresh()->stock,
                            'transaction_code' => $sale->transaction_code,
                            'transaction_sequence' => $sale->transaction_sequence,
                            'original_user_id' => $sale->user_id
                        ]);
                    }
                }

                $sale->delete();

                Log::info('🗑️ Sale deleted by admin with per-user system', [
                    'deleted_sale_id' => $id,
                    'transaction_code' => $sale->transaction_code,
                    'transaction_sequence' => $sale->transaction_sequence,
                    'original_user_id' => $sale->user_id,
                    'had_discount' => $sale->discount_amount > 0,
                    'discount_amount' => $sale->discount_amount,
                    'admin_user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transaksi berhasil dihapus dan stok dikembalikan'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error deleting sale', [
                'sale_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus transaksi'
            ], 500);
        }
    }
}
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
     * ✅ UPDATED: Get next transaction code dengan format baru
     * Format: TR + 2-digit year + 2-digit month + 2-digit day + 6-digit counter (reset harian)
     * Contoh: TR250829000001 (29 Agustus 2025, transaksi ke-1)
     */
    public function getNextTransactionCode()
    {
        try {
            return DB::transaction(function () {
                $today = now();
                $year = $today->format('y'); // 2-digit year
                $month = $today->format('m'); // 2-digit month
                $day = $today->format('d'); // 2-digit day
                $datePrefix = 'TR' . $year . $month . $day;
                
                // 🔥 SOLUSI: Lock dan ambil transaksi tertinggi untuk hari ini
                $latestSale = Sale::lockForUpdate()
                    ->where('transaction_code', 'LIKE', $datePrefix . '%')
                    ->whereDate('created_at', $today->toDateString())
                    ->orderByRaw('CAST(SUBSTRING(transaction_code, 9) AS UNSIGNED) DESC')
                    ->first();

                $nextSequence = 1;
                
                if ($latestSale) {
                    // Extract the sequence number from the last transaction code
                    if (preg_match('/^TR\d{6}(\d{6})$/', $latestSale->transaction_code, $matches)) {
                        $nextSequence = (int) $matches[1] + 1;
                    }
                }

                // 🔥 SAFETY: Pastikan tidak ada collision untuk hari ini
                do {
                    $nextCode = $datePrefix . str_pad($nextSequence, 6, '0', STR_PAD_LEFT);
                    $exists = Sale::where('transaction_code', $nextCode)->exists();
                    if ($exists) {
                        $nextSequence++;
                    }
                } while ($exists);

                Log::info('✅ Next transaction code generated', [
                    'next_code' => $nextCode,
                    'date_prefix' => $datePrefix,
                    'sequence' => $nextSequence,
                    'user_id' => Auth::id(),
                    'latest_code' => $latestSale ? $latestSale->transaction_code : 'none'
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'next_code' => $nextCode
                    ]
                ]);
            });

        } catch (\Exception $e) {
            Log::error('❌ Error generating next transaction code', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan kode transaksi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ UPDATED: Store sale dengan validasi diskon yang ketat
     */
    public function store(Request $request)
    {
        Log::info('🚀 Sale transaction started', [
            'transaction_code' => $request->transaction_code,
            'user_id' => Auth::id(),
            'items_count' => count($request->items ?? []),
            'has_discount' => $request->has('discount') && $request->discount,
            'total_amount' => $request->total_amount
        ]);

        // ✅ VALIDATION - Termasuk validasi diskon
        $validator = Validator::make($request->all(), [
            'transaction_code' => 'required|string|regex:/^TR\d{6}\d{6}$/|unique:sales,transaction_code',
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
            // ✅ VALIDASI DISKON - BARU
            'discount' => 'nullable|array',
            'discount.type' => 'nullable|required_with:discount|in:percentage,fixed',
            'discount.value' => 'nullable|required_with:discount|numeric|min:0',
            'discount.amount' => 'nullable|required_with:discount|numeric|min:0',
        ]);

        // ✅ CUSTOM VALIDATION untuk diskon
        $validator->after(function ($validator) use ($request) {
            if ($request->has('discount') && $request->discount) {
                $discount = $request->discount;

                // Validasi tipe diskon
                if ($discount['type'] === 'percentage' && $discount['value'] > 100) {
                    $validator->errors()->add('discount.value', 'Persentase diskon tidak boleh lebih dari 100%');
                }

                // Hitung subtotal dari items untuk validasi
                $calculatedSubtotal = collect($request->items)->sum('subtotal');

                if ($discount['type'] === 'fixed' && $discount['value'] > $calculatedSubtotal) {
                    $validator->errors()->add('discount.value', 'Nominal diskon tidak boleh lebih besar dari subtotal');
                }

                // Validasi kalkulasi diskon
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
                'transaction_code' => $request->transaction_code
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        // ✅ PROCESS TRANSACTION DENGAN DISKON
        return DB::transaction(function () use ($request) {
            $transactionCode = $request->transaction_code;
            $subtotalCalculated = 0;
            $details = [];

            // ✅ VALIDASI & UPDATE STOCK - Kasir ambil dari produk admin
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

                // ✅ UPDATE STOCK - Kurangi dari stok admin
                $product->stock -= $item['quantity'];
                $product->save();

                Log::info('📦 Stock updated', [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity_sold' => $item['quantity'],
                    'stock_remaining' => $product->stock,
                    'updated_by_kasir' => Auth::id()
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

                Log::info('💰 Discount applied', [
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue,
                    'discount_amount' => $discountAmount,
                    'subtotal' => $subtotalCalculated,
                    'final_total' => $finalTotal
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

            // ✅ CREATE SALE RECORD DENGAN DISKON
            $saleData = [
                'transaction_code' => $transactionCode,
                'date' => $request->date,
                'subtotal_amount' => $subtotalCalculated, // ✅ BARU
                'discount_type' => $discountType, // ✅ BARU
                'discount_value' => $discountValue, // ✅ BARU
                'discount_amount' => $discountAmount, // ✅ BARU
                'total_price' => $request->total_amount,
                'payment_method' => $request->payment_method,
                'cash_received' => $request->cash_received,
                'change_amount' => $request->change_amount,
                'user_id' => Auth::id(),
            ];

            $sale = Sale::create($saleData);

            // ✅ CREATE SALE DETAILS
            foreach ($details as $detail) {
                $detail['sale_id'] = $sale->id;
                SaleDetail::create($detail);
            }

            Log::info('✅ Transaction completed successfully', [
                'sale_id' => $sale->id,
                'transaction_code' => $sale->transaction_code,
                'subtotal_price' => $sale->subtotal_amount,
                'discount_amount' => $sale->discount_amount,
                'total_price' => $sale->total_price,
                'payment_method' => $sale->payment_method,
                'kasir_id' => Auth::id()
            ]);

            // ✅ RETURN RESPONSE DENGAN DATA DISKON
            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil disimpan',
                'data' => [
                    'id' => $sale->id,
                    'transaction_code' => $sale->transaction_code,
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
     * ✅ GET ALL SALES - Admin dan kasir bisa lihat (dengan data diskon)
     */
    public function index()
    {
        try {
            $user = Auth::user();
            $query = Sale::with(['details.product', 'user']);

            $sales = $query->orderBy('id', 'desc')->get();

            // ✅ TAMBAHKAN INFO DISKON KE RESPONSE
            $salesWithDiscountInfo = $sales->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'transaction_code' => $sale->transaction_code,
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

    public function show($id)
    {
        try {
            $sale = Sale::with(['details.product', 'user'])->find($id);

            if (!$sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaksi tidak ditemukan'
                ], 404);
            }

            // ✅ TAMBAHKAN INFO DISKON KE RESPONSE
            $saleWithDiscountInfo = [
                'id' => $sale->id,
                'transaction_code' => $sale->transaction_code,
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
     * ✅ SALES REPORT DENGAN STATISTIK DISKON - Hanya admin yang bisa akses
     */
    public function report(Request $request)
    {
        try {
            // ✅ Check permission
            $user = Auth::user();
            if (!$user || !$user->role || $user->role->name !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat mengakses laporan.'
                ], 403);
            }

            $sales = Sale::with(['details.product', 'user'])
                ->when($request->has('start_date'), function ($query) use ($request) {
                    $query->whereDate('date', '>=', $request->start_date);
                })
                ->when($request->has('end_date'), function ($query) use ($request) {
                    $query->whereDate('date', '<=', $request->end_date);
                })
                ->orderBy('id', 'desc')
                ->get();

            $totalRevenue = $sales->sum('total_price');
            $totalTransactions = $sales->count();
            $totalItemsSold = $sales->flatMap->details->sum('quantity');

            // ✅ STATISTIK DISKON - BARU
            $discountStatistics = Sale::getDiscountStatistics($request->start_date, $request->end_date);

            Log::info('📊 Sales report with discount generated by admin', [
                'total_transactions' => $totalTransactions,
                'total_revenue' => $totalRevenue,
                'total_discount_amount' => $discountStatistics['total_discount_amount'],
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
                    'discount_statistics' => $discountStatistics, // ✅ BARU
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
     * ✅ ENDPOINT BARU: Laporan khusus diskon - Hanya admin
     */
    public function discountReport(Request $request)
    {
        try {
            // ✅ Check permission
            $user = Auth::user();
            if (!$user || !$user->role || $user->role->name !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya admin yang dapat mengakses laporan diskon.'
                ], 403);
            }

            $discountReport = Sale::getDiscountReport($request->start_date, $request->end_date);

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
     * ✅ ENDPOINT BARU: Statistik diskon untuk dashboard - Admin & Kasir
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

            // Default ke 30 hari terakhir jika tidak ada filter
            $endDate = $request->end_date ?: now()->format('Y-m-d');
            $startDate = $request->start_date ?: now()->subDays(30)->format('Y-m-d');

            $stats = Sale::getDiscountStatistics($startDate, $endDate);

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
     * ✅ UPDATE SALE - Jika diperlukan (tetap tidak diizinkan)
     */
    public function update(Request $request, $id)
    {
        return response()->json([
            'success' => false,
            'message' => 'Update transaksi tidak diizinkan untuk menjaga integritas data'
        ], 403);
    }

    /**
     * ✅ DELETE SALE DENGAN RESTORE STOCK - Hanya admin
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
                        $product->stock += $detail->quantity;
                        $product->save();

                        Log::info('📦 Stock restored after deletion', [
                            'product_id' => $product->id,
                            'quantity_restored' => $detail->quantity,
                            'new_stock' => $product->stock
                        ]);
                    }
                }

                // ✅ DELETE SALE (cascade akan hapus details)
                $sale->delete();

                Log::info('🗑️ Sale with discount deleted by admin', [
                    'deleted_sale_id' => $id,
                    'transaction_code' => $sale->transaction_code,
                    'had_discount' => $sale->discount_amount > 0,
                    'discount_amount' => $sale->discount_amount,
                    'discount_type' => $sale->discount_type,
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

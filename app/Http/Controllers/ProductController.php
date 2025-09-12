<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    private function getUserId()
    {
        try {
            return Auth::check() ? Auth::id() : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function index(Request $request)
    {
        try {
            $query = Product::with(['jenis', 'distributor', 'satuan']);

            if ($request->has('jenis_id')) {
                $query->where('jenis_id', $request->jenis_id);
            }

            if ($request->has('distributor_id')) {
                $query->where('distributor_id', $request->distributor_id);
            }

            if ($request->has('min_stock')) {
                $query->where('stock', '>=', $request->min_stock);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('kode_barang', 'like', '%' . $search . '%');
                });
            }

            if ($request->has('low_stock')) {
                $threshold = $request->get('low_stock_threshold', 10);
                $query->where('stock', '<=', $threshold);
            }

            if ($request->has('out_of_stock')) {
                $query->where('stock', 0);
            }

            $products = $query->orderBy('created_at', 'desc')->get();

            $products->each(function ($product) {
                if ($product->photo) {
                    $product->photo_url = asset('storage/' . $product->photo);
                } else {
                    $product->photo_url = null;
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Data produk berhasil diambil',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching products', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function handlePhotoUpload(Request $request, $oldPhoto = null)
    {
        if ($request->hasFile('photo')) {
            if ($oldPhoto && Storage::disk('public')->exists($oldPhoto)) {
                Storage::disk('public')->delete($oldPhoto);
            }

            $file = $request->file('photo');
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $fileName, 'public');

            return $path;
        }

        return $oldPhoto;
    }

    public function store(Request $request)
    {
        Log::info('Product store request', [
            'all_data' => $request->except(['photo']),
            'has_photo' => $request->hasFile('photo'),
            'photo_size' => $request->hasFile('photo') ? $request->file('photo')->getSize() : 0
        ]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jenis_id' => 'required|exists:jenis,id',
            'satuan_id' => 'required|exists:satuan,id',
            'distributor_id' => 'required|exists:distributors,id',
            'stock' => 'required|integer|min:0',
            'selling_price' => 'required|numeric|min:0',
        ], [
            'name.required' => 'Nama produk harus diisi',
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'Format gambar harus JPEG, PNG, JPG, atau GIF',
            'photo.max' => 'Ukuran gambar maksimal 2MB',
            'jenis_id.required' => 'Jenis harus dipilih',
            'jenis_id.exists' => 'Jenis yang dipilih tidak valid',
            'satuan_id.required' => 'Satuan harus dipilih',
            'satuan_id.exists' => 'Satuan yang dipilih tidak valid',
            'distributor_id.required' => 'Distributor harus dipilih',
            'distributor_id.exists' => 'Distributor yang dipilih tidak valid',
            'stock.required' => 'Stok harus diisi',
            'stock.integer' => 'Stok harus berupa angka',
            'stock.min' => 'Stok tidak boleh kurang dari 0',
            'selling_price.required' => 'Harga jual harus diisi',
            'selling_price.numeric' => 'Harga jual harus berupa angka',
            'selling_price.min' => 'Harga jual tidak boleh kurang dari 0',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed for product creation', [
                'errors' => $validator->errors(),
                'request_data' => $request->except(['photo'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $kodeBarang = $this->generateKodeBarang();
            $photoPath = $this->handlePhotoUpload($request);

            $product = Product::create([
                'kode_barang' => $kodeBarang,
                'name' => $request->name,
                'photo' => $photoPath,
                'jenis_id' => $request->jenis_id,
                'satuan_id' => $request->satuan_id,
                'distributor_id' => $request->distributor_id,
                'stock' => $request->stock,
                'selling_price' => $request->selling_price
            ]);

            // Jika ada stok awal, buat movement record
            if ($request->stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $request->stock,
                    'distributor_id' => $request->distributor_id,
                    'notes' => 'Stok awal produk',
                    'user_id' => Auth::id()
                ]);
            }

            $product->load(['jenis', 'distributor', 'satuan']);

            if ($product->photo) {
                $product->photo_url = asset('storage/' . $product->photo);
            } else {
                $product->photo_url = null;
            }

            Log::info('Product created successfully', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'initial_stock' => $request->stock,
                'created_by' => $this->getUserId()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dibuat',
                'data' => $product
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($photoPath) && $photoPath && Storage::disk('public')->exists($photoPath)) {
                Storage::disk('public')->delete($photoPath);
            }

            Log::error('Error creating product', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['photo'])
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function generateKodeBarang()
    {
        $last = Product::orderBy('id', 'desc')->first();
        $nextId = $last ? $last->id + 1 : 1;

        do {
            $code = 'BR' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
            $exists = Product::where('kode_barang', $code)->exists();
            if ($exists) {
                $nextId++;
            }
        } while ($exists);

        return $code;
    }

    public function show($id)
    {
        try {
            $product = Product::with(['jenis', 'distributor', 'satuan'])->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }

            if ($product->photo) {
                $product->photo_url = asset('storage/' . $product->photo);
            } else {
                $product->photo_url = null;
            }

            return response()->json([
                'success' => true,
                'data' => $product
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching product', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        Log::info('Product update request', [
            'product_id' => $id,
            'all_data' => $request->except(['photo']),
            'has_photo' => $request->hasFile('photo'),
            'photo_size' => $request->hasFile('photo') ? $request->file('photo')->getSize() : 0
        ]);

        // UPDATED VALIDATION: Hapus 'stock' dari rules
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'jenis_id' => 'required|exists:jenis,id',
            'satuan_id' => 'required|exists:satuan,id',
            'distributor_id' => 'required|exists:distributors,id',
            'selling_price' => 'required|numeric|min:0',
        ], [
            'name.required' => 'Nama produk harus diisi',
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'Format gambar harus JPEG, PNG, JPG, atau GIF',
            'photo.max' => 'Ukuran gambar maksimal 2MB',
            'jenis_id.required' => 'Jenis harus dipilih',
            'jenis_id.exists' => 'Jenis yang dipilih tidak valid',
            'satuan_id.required' => 'Satuan harus dipilih',
            'satuan_id.exists' => 'Satuan yang dipilih tidak valid',
            'distributor_id.required' => 'Distributor harus dipilih',
            'distributor_id.exists' => 'Distributor yang dipilih tidak valid',
            'selling_price.required' => 'Harga jual harus diisi',
            'selling_price.numeric' => 'Harga jual harus berupa angka',
            'selling_price.min' => 'Harga jual tidak boleh kurang dari 0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }

            $oldPhoto = $product->photo;
            $photoPath = $this->handlePhotoUpload($request, $oldPhoto);

            // UPDATED: Hapus 'stock' dari update array
            $product->update([
                'name' => $request->name,
                'photo' => $photoPath,
                'jenis_id' => $request->jenis_id,
                'satuan_id' => $request->satuan_id,
                'distributor_id' => $request->distributor_id,
                'selling_price' => $request->selling_price
            ]);

            $product->load(['jenis', 'distributor', 'satuan']);

            if ($product->photo) {
                $product->photo_url = asset('storage/' . $product->photo);
            } else {
                $product->photo_url = null;
            }

            Log::info('Product updated successfully', [
                'product_id' => $product->id,
                'updated_by' => $this->getUserId()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil diperbarui',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating product', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }

            $productName = $product->name;

            // Hard delete dengan protection di model boot method
            $product->delete();

            Log::info('Product deleted', [
                'product_id' => $id,
                'product_name' => $productName,
                'deleted_by' => $this->getUserId()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting product', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus produk: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // NEW METHOD: Tambah stok dengan tracking
    public function addStockWithTracking(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
            'distributor_id' => 'required|exists:distributors,id',
            'notes' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }

            $movement = $product->addStock(
                $request->quantity,
                $request->distributor_id,
                $request->notes
            );

            $product->load(['jenis', 'distributor', 'satuan']);

            if ($product->photo) {
                $product->photo_url = asset('storage/' . $product->photo);
            } else {
                $product->photo_url = null;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => $product,
                    'movement' => $movement->load(['distributor', 'user'])
                ],
                'message' => "Stok berhasil ditambah +{$request->quantity}"
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding stock with tracking', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $this->getUserId()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambah stok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // NEW METHOD: Get stock history for specific product
    public function getStockHistory($id, Request $request)
    {
        try {
            $product = Product::with(['jenis', 'satuan', 'distributor'])->findOrFail($id);

            $query = $product->stockMovements()
                           ->with(['distributor', 'user'])
                           ->orderBy('created_at', 'desc');

            // Filter tanggal jika ada
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
            }

            $movements = $query->paginate($request->get('per_page', 10));

            return response()->json([
                'success' => true,
                'data' => [
                    'product' => $product,
                    'movements' => $movements
                ],
                'message' => 'Riwayat stok berhasil diambil'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching stock history', [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error mengambil riwayat stok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ FIXED: Legacy method untuk compatibility dengan sistem lama
    public function simpleAddStock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'additional_stock' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan'
                ], 404);
            }

            $oldStock = $product->stock;
            $additionalStock = $request->additional_stock;

            // ✅ FIXED: Use new method dengan default distributor dan notes yang benar
            $movement = $product->addStock(
                $additionalStock,
                $product->distributor_id, // Use product's default distributor
                'Penambahan stok via sistem lama'
            );

            $product->load(['jenis', 'distributor', 'satuan']);

            if ($product->photo) {
                $product->photo_url = asset('storage/' . $product->photo);
            } else {
                $product->photo_url = null;
            }

            Log::info('Stock added via legacy method', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'old_stock' => $oldStock,
                'added_stock' => $additionalStock,
                'new_stock' => $product->stock,
                'updated_by' => $this->getUserId()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Stok berhasil ditambah +{$additionalStock}",
                'data' => $product
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error adding stock via legacy method', [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $this->getUserId()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menambah stok',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLowStock(Request $request)
    {
        $threshold = $request->get('threshold', 10);

        try {
            $lowStockProducts = Product::with(['jenis', 'distributor', 'satuan'])
                ->where('stock', '<=', $threshold)
                ->orderBy('stock', 'asc')
                ->get();

            $lowStockProducts->each(function ($product) {
                if ($product->photo) {
                    $product->photo_url = asset('storage/' . $product->photo);
                } else {
                    $product->photo_url = null;
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Data produk stok rendah berhasil diambil',
                'data' => $lowStockProducts,
                'threshold' => $threshold,
                'count' => $lowStockProducts->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching low stock products', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data produk stok rendah',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getOutOfStock()
    {
        try {
            $outOfStockProducts = Product::with(['jenis', 'distributor', 'satuan'])
                ->where('stock', 0)
                ->orderBy('updated_at', 'desc')
                ->get();

            $outOfStockProducts->each(function ($product) {
                if ($product->photo) {
                    $product->photo_url = asset('storage/' . $product->photo);
                } else {
                    $product->photo_url = null;
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Data produk habis stok berhasil diambil',
                'data' => $outOfStockProducts,
                'count' => $outOfStockProducts->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching out of stock products', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data produk habis stok',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

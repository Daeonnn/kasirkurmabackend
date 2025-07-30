<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\JenisController;
use App\Http\Controllers\SatuanController;
use App\Http\Controllers\DistributorController;
use App\Http\Controllers\UserController;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $user->load('role');

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => [
                        'id' => $user->role->id,
                        'name' => $user->role->name
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user data',
                'error' => $e->getMessage()
            ], 500);
        }
    });

    // ✅ DASHBOARD ROUTES
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/chart-data/{type}', [DashboardController::class, 'getChartData']);
    Route::get('/dashboard/recent-activities', [DashboardController::class, 'getRecentActivities']);
    Route::get('/dashboard/low-stock', [DashboardController::class, 'getLowStockProducts']);

    // ✅ ROUTES KHUSUS ADMIN
    Route::middleware('checkrole:admin')->group(function () {
        // Master Data Routes
        Route::apiResource('jenis', JenisController::class);
        Route::apiResource('satuan', SatuanController::class);
        Route::apiResource('distributor', DistributorController::class);
        Route::apiResource('users', UserController::class);
        Route::get('/users/role/{roleId}', [UserController::class, 'getByRole']);

        // ✅ SALES ROUTES - ADMIN ONLY
        Route::get('/sales/report', [SaleController::class, 'report']);
        Route::delete('/sales/{id}', [SaleController::class, 'destroy']);

        // ✅ DISCOUNT ROUTES - BARU - ADMIN ONLY
        Route::get('/sales/discount-report', [SaleController::class, 'discountReport']);

        // Legacy Route
        Route::get('/laporan/transaksi', function () {
            return response()->json(['message' => 'Laporan transaksi']);
        });
    });

    // ✅ ROUTES UNTUK ADMIN & KASIR
    Route::middleware('checkrole:admin,kasir')->group(function () {

        // ✅ PRODUCT ROUTES
        Route::apiResource('products', ProductController::class);
        Route::patch('/products/{id}/add-stock', [ProductController::class, 'simpleAddStock']);
        Route::get('/products/low-stock', [ProductController::class, 'getLowStock']);
        Route::get('/products/out-of-stock', [ProductController::class, 'getOutOfStock']);

        // ✅ SALES ROUTES - ADMIN & KASIR (Updated untuk diskon)
        Route::get('/sales/next-transaction-code', [SaleController::class, 'getNextTransactionCode']);
        Route::get('/sales', [SaleController::class, 'index']); // ✅ Updated: Include discount info
        Route::post('/sales', [SaleController::class, 'store']); // ✅ Updated: Handle discount
        Route::get('/sales/{id}', [SaleController::class, 'show']); // ✅ Updated: Include discount info
        Route::put('/sales/{id}', [SaleController::class, 'update']); // Tetap tidak diizinkan

        // ✅ DISCOUNT STATS ROUTE - BARU - ADMIN & KASIR
        Route::get('/sales/discount-stats', [SaleController::class, 'discountStats']);

        // ✅ TRANSACTION LEGACY ROUTES (untuk backward compatibility)
        Route::post('/transaksi', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Transaksi created successfully',
                'data' => $request->all()
            ]);
        });

        Route::get('/transaksi/history', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Transaksi history',
                'data' => []
            ]);
        });

        // ✅ MASTER DATA LIST ROUTES
        Route::get('/jenis/list', [JenisController::class, 'index']);
        Route::get('/distributor/list', [DistributorController::class, 'index']);
        Route::get('/satuan/list', [SatuanController::class, 'index']);
    });
});

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});

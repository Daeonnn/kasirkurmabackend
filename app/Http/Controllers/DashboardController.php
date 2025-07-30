<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Merek;
use App\Models\Distributor;
use App\Models\Sale;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function getStats(Request $request)
    {
        try {
            $user = $request->user();
            
            // Basic stats
            $totalProducts = Product::count();
            $totalMerek = Merek::count();
            $totalDistributors = Distributor::count();
            
            // Sales stats (filtered by user role)
            $salesQuery = Sale::query();
            if (!$user->isAdmin()) {
                $salesQuery->where('user_id', $user->id);
            }
            
            $totalSales = $salesQuery->count();
            $completedSales = $salesQuery->clone()->where('status', Sale::STATUS_COMPLETED)->count();
            $pendingSales = $salesQuery->clone()->where('status', Sale::STATUS_PENDING)->count();
            
            // Today's stats
            $todaySales = $salesQuery->clone()
                ->whereDate('sale_date', Carbon::today())
                ->where('status', '!=', Sale::STATUS_CANCELLED)
                ->sum('quantity');
            
            // This month's stats
            $thisMonthSales = $salesQuery->clone()
                ->whereMonth('sale_date', Carbon::now()->month)
                ->whereYear('sale_date', Carbon::now()->year)
                ->where('status', '!=', Sale::STATUS_CANCELLED)
                ->sum('quantity');
            
            // Low stock count
            $lowStockCount = Product::where('stock', '<=', 10)->count();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'products' => [
                        'total' => $totalProducts,
                        'low_stock' => $lowStockCount
                    ],
                    'merek' => [
                        'total' => $totalMerek
                    ],
                    'distributors' => [
                        'total' => $totalDistributors
                    ],
                    'sales' => [
                        'total' => $totalSales,
                        'completed' => $completedSales,
                        'pending' => $pendingSales,
                        'today' => $todaySales,
                        'this_month' => $thisMonthSales
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chart data based on type
     */
    public function getChartData(Request $request, $type)
    {
        try {
            $user = $request->user();
            
            switch ($type) {
                case 'sales':
                    return $this->getSalesChartData($user);
                case 'products':
                    return $this->getProductsChartData();
                case 'distributors':
                    return $this->getDistributorsChartData();
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Tipe chart tidak valid'
                    ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data chart',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales chart data (last 7 days)
     */
    private function getSalesChartData($user)
    {
        $dates = [];
        $sales = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dates[] = $date->format('M d');
            
            $query = Sale::whereDate('sale_date', $date)
                ->where('status', '!=', Sale::STATUS_CANCELLED);
            
            if (!$user->isAdmin()) {
                $query->where('user_id', $user->id);
            }
            
            $dailySales = $query->sum('quantity');
            $sales[] = $dailySales;
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'labels' => $dates,
                'datasets' => [
                    [
                        'label' => 'Penjualan',
                        'data' => $sales,
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'fill' => true
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get products chart data (stock levels)
     */
    private function getProductsChartData()
    {
        $products = Product::with('merek')
            ->orderBy('stock', 'asc')
            ->limit(10)
            ->get();
            
        $labels = $products->map(function($product) {
            return $product->name . ' (' . ($product->merek->name ?? 'Unknown') . ')';
        });
        
        $data = $products->pluck('stock');
        
        return response()->json([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Stock',
                        'data' => $data,
                        'backgroundColor' => [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF',
                            '#FF9F40',
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0'
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get distributors chart data
     */
    private function getDistributorsChartData()
    {
        $distributors = Distributor::withCount('products')
            ->having('products_count', '>', 0)
            ->get();
            
        $labels = $distributors->pluck('name');
        $data = $distributors->pluck('products_count');
        
        return response()->json([
            'success' => true,
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Jumlah Produk',
                        'data' => $data,
                        'backgroundColor' => [
                            '#FF6384',
                            '#36A2EB',
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF',
                            '#FF9F40'
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities(Request $request)
    {
        try {
            $user = $request->user();
            
            // Recent sales
            $salesQuery = Sale::with(['product.merek', 'user'])
                ->latest()
                ->limit(5);
                
            if (!$user->isAdmin()) {
                $salesQuery->where('user_id', $user->id);
            }
            
            $recentSales = $salesQuery->get();
            
            // Recent purchases (only for admin)
            $recentPurchases = [];
            if ($user->isAdmin()) {
                $recentPurchases = Purchase::with(['product.merek', 'user'])
                    ->latest()
                    ->limit(5)
                    ->get();
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'sales' => $recentSales,
                    'purchases' => $recentPurchases
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil aktivitas terbaru',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(Request $request)
    {
        try {
            $threshold = $request->get('threshold', 10);
            
            $lowStockProducts = Product::with(['merek', 'distributor'])
                ->where('stock', '<=', $threshold)
                ->orderBy('stock', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $lowStockProducts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil produk stok rendah',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
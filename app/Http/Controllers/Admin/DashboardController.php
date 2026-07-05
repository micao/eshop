<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard with inventory metrics.
     */
    public function index(): Response
    {
        $totalProducts = Product::where('status', 'active')->count();
        $totalStock = (int) Variant::sum('inventory_quantity');

        $lowStockCount = Variant::where('track_inventory', true)
            ->where('continue_selling_out_of_stock', false)
            ->where('inventory_quantity', '>', 0)
            ->where('inventory_quantity', '<=', 10)
            ->count();

        $outOfStockCount = Variant::where('track_inventory', true)
            ->where('continue_selling_out_of_stock', false)
            ->where('inventory_quantity', '<=', 0)
            ->count();

        // Get top 10 low stock variants (sorted by inventory quantity ascending)
        $lowStockVariants = Variant::with('product')
            ->where('track_inventory', true)
            ->where('continue_selling_out_of_stock', false)
            ->where('inventory_quantity', '<=', 10)
            ->orderBy('inventory_quantity', 'asc')
            ->take(10)
            ->get();

        // Retrieve inventory stats compiled across root categories
        $categoriesStats = Category::whereNull('parent_id')
            ->with(['products.variants'])
            ->get()
            ->map(function ($category) {
                // Compile total stock and product counts recursively or under direct children
                $productsQuery = Product::where('status', 'active')
                    ->where(function ($query) use ($category) {
                        $query->where('category_id', $category->id)
                            ->orWhereIn('category_id', $category->children()->pluck('id'));
                    });

                $productCount = $productsQuery->count();

                $stockSum = 0;
                foreach ($productsQuery->with('variants')->get() as $prod) {
                    $stockSum += $prod->variants->sum('inventory_quantity');
                }

                return [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'products_count' => $productCount,
                    'total_stock' => $stockSum,
                ];
            });

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'totalProducts' => $totalProducts,
                'totalStock' => $totalStock,
                'lowStockCount' => $lowStockCount,
                'outOfStockCount' => $outOfStockCount,
            ],
            'lowStockVariants' => $lowStockVariants,
            'categoriesStats' => $categoriesStats,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    /**
     * Display the store homepage.
     */
    public function index(): Response
    {
        // Fetch primary categories
        $categories = Category::whereNull('parent_id')
            ->withCount('products')
            ->get();

        // Fetch latest active products with variants
        $newArrivals = Product::with(['category', 'variants'])
            ->where('status', 'active')
            ->latest()
            ->take(8)
            ->get();

        return Inertia::render('Welcome', [
            'categories' => $categories,
            'newArrivals' => $newArrivals,
        ]);
    }

    /**
     * Display the catalog search/filter listing.
     */
    public function catalog(Request $request): Response
    {
        // 1. Elasticsearch search or standard DB query initialization
        if ($request->filled('search')) {
            $search = $request->input('search');
            $scoutIds = Product::search($search)->keys()->toArray();

            if (empty($scoutIds)) {
                $query = Product::whereRaw('1 = 0');
            } else {
                $query = Product::with(['category', 'variants', 'brand', 'supplier'])
                    ->whereIn('id', $scoutIds)
                    ->where('status', 'active');

                // If not sorting by price, order by Elasticsearch's relevance sequence
                if (! $request->filled('sort') || $request->input('sort') === 'latest') {
                    $driver = $query->getConnection()->getDriverName();
                    if ($driver === 'sqlite') {
                        $cases = [];
                        foreach ($scoutIds as $index => $id) {
                            $cases[] = "WHEN id = {$id} THEN {$index}";
                        }
                        $caseSql = 'CASE '.implode(' ', $cases).' END';
                        $query->orderByRaw($caseSql);
                    } else {
                        $idsOrder = implode(',', $scoutIds);
                        $query->orderByRaw("FIELD(id, {$idsOrder})");
                    }
                }
            }
        } else {
            $query = Product::with(['category', 'variants', 'brand', 'supplier'])
                ->where('status', 'active');
        }

        // 2. Filter by category slug
        if ($request->filled('category')) {
            $categorySlug = $request->input('category');
            $query->whereHas('category', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug)
                    ->orWhereHas('parent', function ($qp) use ($categorySlug) {
                        $qp->where('slug', $categorySlug);
                    });
            });
        }

        // 3. Price range filters (matches variant pricing)
        if ($request->filled('price_min')) {
            $query->whereHas('variants', function ($q) use ($request) {
                $q->where('price', '>=', floatval($request->input('price_min')));
            });
        }
        if ($request->filled('price_max')) {
            $query->whereHas('variants', function ($q) use ($request) {
                $q->where('price', '<=', floatval($request->input('price_max')));
            });
        }

        // 4. In-stock availability filter
        if ($request->boolean('in_stock')) {
            $query->whereHas('variants', function ($q) {
                $q->where('inventory_quantity', '>', 0);
            });
        }

        // 4.5 Filter by brand slug
        if ($request->filled('brand')) {
            $brandSlug = $request->input('brand');
            $query->whereHas('brand', function ($q) use ($brandSlug) {
                $q->where('slug', $brandSlug);
            });
        }

        // 5. Order/Sort configurations
        $sort = $request->input('sort', 'latest');
        if ($sort === 'price_asc') {
            $query->orderBy(
                Variant::select('price')
                    ->whereColumn('variants.product_id', 'products.id')
                    ->orderBy('price', 'asc')
                    ->limit(1),
                'asc'
            );
        } elseif ($sort === 'price_desc') {
            $query->orderBy(
                Variant::select('price')
                    ->whereColumn('variants.product_id', 'products.id')
                    ->orderBy('price', 'asc')
                    ->limit(1),
                'desc'
            );
        } else {
            $query->latest();
        }

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::whereNull('parent_id')->with('children')->get();
        $brands = Brand::orderBy('name')->get();

        return Inertia::render('catalog/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'filters' => $request->only(['category', 'search', 'price_min', 'price_max', 'in_stock', 'sort', 'brand']),
        ]);
    }

    /**
     * Display a specific product details page.
     */
    public function productShow(string $slug): Response
    {
        $product = Product::with(['category', 'variants'])
            ->where('slug', $slug)
            ->where('status', 'active')
            ->firstOrFail();

        return Inertia::render('catalog/Show', [
            'product' => $product,
        ]);
    }

    /**
     * Display the shopper cart page.
     */
    public function cart(): Response
    {
        return Inertia::render('catalog/Cart');
    }

    /**
     * Display the checkout page.
     */
    public function checkout(Request $request): Response
    {
        $categories = Category::whereNull('parent_id')->get();

        return Inertia::render('catalog/Checkout', [
            'categories' => $categories,
        ]);
    }

    /**
     * Display the checkout success page.
     */
    public function checkoutSuccess(Request $request): Response
    {
        $orderNumber = $request->query('order_number');
        $order = Order::where('user_id', auth()->id())
            ->where('order_number', $orderNumber)
            ->with('items.variant.product')
            ->firstOrFail();

        $categories = Category::whereNull('parent_id')->get();

        return Inertia::render('catalog/CheckoutSuccess', [
            'categories' => $categories,
            'order' => $order,
        ]);
    }
}

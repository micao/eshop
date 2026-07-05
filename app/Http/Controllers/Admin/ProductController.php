<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     *
     * @return Response
     */
    public function index(Request $request): Response
    {
        $query = Product::with(['category', 'variants', 'brand', 'supplier']);

        // 1. Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // 2. Filter by brand
        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->input('brand_id'));
        }

        // 3. Filter by supplier
        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        // 4. Price range filters (matches variant pricing)
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

        $products = $query->orderBy('created_at', 'desc')->paginate(12)->withQueryString();

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            'categories' => \App\Models\Category::all(),
            'brands' => \App\Models\Brand::orderBy('name')->get(),
            'suppliers' => \App\Models\Supplier::orderBy('name')->get(),
            'filters' => $request->only(['category_id', 'brand_id', 'supplier_id', 'price_min', 'price_max']),
        ]);
    }

    /**
     * Update the specified product in storage.
     *
     * @param Request $request
     * @param Product $product
     * @return RedirectResponse
     */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|string|in:active,draft,archived',
            'summary' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        $product->update($validated);

        return redirect()->back()->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified product from storage (soft delete).
     *
     * @param Product $product
     * @return RedirectResponse
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }
}

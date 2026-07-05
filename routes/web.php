<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/catalog', [StorefrontController::class, 'catalog'])->name('catalog');
Route::get('/catalog/products/{product:slug}', [StorefrontController::class, 'productShow'])->name('storefront.products.show');
Route::get('/cart', [StorefrontController::class, 'cart'])->name('storefront.cart');

Route::post('/api/cart/details', [\App\Http\Controllers\Api\CartController::class, 'guestDetails']);
Route::post('/api/webhooks/stripe', [\App\Http\Controllers\Api\StripeWebhookController::class, 'handle']);
Route::middleware(['auth'])->group(function () {
    Route::post('/api/graphql', [\App\Http\Controllers\Api\GraphQLController::class, 'handle']);
    Route::get('/api/cart', [\App\Http\Controllers\Api\CartController::class, 'index']);
    Route::post('/api/cart', [\App\Http\Controllers\Api\CartController::class, 'store']);
    Route::put('/api/cart/items/{cartItem}', [\App\Http\Controllers\Api\CartController::class, 'update']);
    Route::delete('/api/cart/items/{cartItem}', [\App\Http\Controllers\Api\CartController::class, 'destroy']);
    Route::delete('/api/cart', [\App\Http\Controllers\Api\CartController::class, 'clear']);
    Route::post('/api/cart/merge', [\App\Http\Controllers\Api\CartController::class, 'merge']);

    // Address Book routes
    Route::get('/api/addresses', [\App\Http\Controllers\Api\AddressController::class, 'index']);
    Route::post('/api/addresses', [\App\Http\Controllers\Api\AddressController::class, 'store']);
    Route::delete('/api/addresses/{address}', [\App\Http\Controllers\Api\AddressController::class, 'destroy']);

    // Checkout / Shipping routes
    Route::get('/api/checkout/shipping-rates', [\App\Http\Controllers\Api\CheckoutController::class, 'getShippingRates']);
    Route::post('/api/checkout', [\App\Http\Controllers\Api\CheckoutController::class, 'store']);

    // Storefront Checkout page views
    Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('storefront.checkout');
    Route::get('/checkout/success', [StorefrontController::class, 'checkoutSuccess'])->name('storefront.checkout.success');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    
    Route::get('orders', [\App\Http\Controllers\Admin\OrderController::class, 'index'])->name('admin.orders.index');
});

require __DIR__.'/settings.php';

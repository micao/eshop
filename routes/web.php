<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\GraphQLController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StorefrontController::class, 'index'])->name('home');
Route::get('/catalog', [StorefrontController::class, 'catalog'])->name('catalog');
Route::get('/catalog/products/{product:slug}', [StorefrontController::class, 'productShow'])->name('storefront.products.show');
Route::get('/cart', [StorefrontController::class, 'cart'])->name('storefront.cart');

Route::post('/api/cart/details', [CartController::class, 'guestDetails']);
Route::post('/api/webhooks/stripe', [StripeWebhookController::class, 'handle']);
Route::middleware(['auth'])->group(function () {
    Route::post('/api/graphql', [GraphQLController::class, 'handle']);
    Route::get('/api/cart', [CartController::class, 'index']);
    Route::post('/api/cart', [CartController::class, 'store']);
    Route::put('/api/cart/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('/api/cart/items/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('/api/cart', [CartController::class, 'clear']);
    Route::post('/api/cart/merge', [CartController::class, 'merge']);

    // Address Book routes
    Route::get('/api/addresses', [AddressController::class, 'index']);
    Route::post('/api/addresses', [AddressController::class, 'store']);
    Route::delete('/api/addresses/{address}', [AddressController::class, 'destroy']);

    // Checkout / Shipping routes
    Route::get('/api/checkout/shipping-rates', [CheckoutController::class, 'getShippingRates']);
    Route::post('/api/checkout', [CheckoutController::class, 'store']);

    // Storefront Checkout page views
    Route::get('/checkout', [StorefrontController::class, 'checkout'])->name('storefront.checkout');
    Route::get('/checkout/success', [StorefrontController::class, 'checkoutSuccess'])->name('storefront.checkout.success');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('orders', [OrderController::class, 'index'])->name('admin.orders.index');
});

require __DIR__.'/settings.php';

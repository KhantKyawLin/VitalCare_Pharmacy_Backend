<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;

Route::group([
    'prefix' => 'auth'
], function ($router) {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);

    Route::group(['middleware' => 'auth:api'], function() {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        
        // Cart Routes
        Route::get('cart', [\App\Http\Controllers\CartController::class, 'index']);
        Route::post('cart/add', [\App\Http\Controllers\CartController::class, 'add']);
        Route::delete('cart/remove/{product_id}', [\App\Http\Controllers\CartController::class, 'remove']);
        Route::patch('cart/update/{product_id}', [\App\Http\Controllers\CartController::class, 'updateQuantity']);

        // Wishlist Routes
        Route::get('wishlist', [\App\Http\Controllers\WishlistController::class, 'index']);
        Route::post('wishlist/add', [\App\Http\Controllers\WishlistController::class, 'add']);
        Route::delete('wishlist/remove/{product_id}', [\App\Http\Controllers\WishlistController::class, 'remove']);

        // Order Routes
        Route::get('orders', [\App\Http\Controllers\OrderController::class, 'index']);
        Route::get('orders/{id}', [\App\Http\Controllers\OrderController::class, 'show']);
        Route::post('checkout', [\App\Http\Controllers\OrderController::class, 'checkout']);
    });
});

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/health-tips', [\App\Http\Controllers\HealthTipController::class, 'index']);
Route::get('/health-tips/{id}', [\App\Http\Controllers\HealthTipController::class, 'show']);

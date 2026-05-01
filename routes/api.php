<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\HealthTipController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\SearchController;

// --- Import Admin Controllers ---
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminUnitController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminSupplierController;
use App\Http\Controllers\Admin\AdminInventoryController;
use App\Http\Controllers\Admin\AdminReportController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminHealthTipController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminExpiredItemController;
use App\Http\Controllers\Admin\AdminExternalTransactionController;

/*
|--------------------------------------------------------------------------
| PUBLIC ROUTES (No Authentication Required)
|--------------------------------------------------------------------------
*/

// Products (public browsing)
Route::get('/products/top-sellers', [ProductController::class, 'topSellers']);
Route::get('/products/special-offers', [ProductController::class, 'specialOffers']);
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);

// Health Tips (public browsing)
Route::get('/health-tips', [HealthTipController::class, 'index']);
Route::get('/health-tips/{id}', [HealthTipController::class, 'show']);
Route::post('/health-tips/{id}/feedback', [FeedbackController::class, 'store'])->middleware('auth:api');

// Health Tip Feedbacks (public read)
Route::get('/health-tips/{id}/feedbacks', [FeedbackController::class, 'index']);

// Contact Us (public submit - rate limited)
Route::post('/contact', [ContactUsController::class, 'store'])->middleware('throttle:10,1');

// Site Settings (public read for frontend branding)
Route::get('/site-settings', [AdminDashboardController::class, 'getSettings']);

// Forgot Password (public - rate limited)
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::group(['prefix' => 'auth'], function ($router) {
    // Public auth
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');

    // Authenticated user routes
    Route::group(['middleware' => 'auth:api'], function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);

        // Cart Routes
        Route::get('cart', [\App\Http\Controllers\CartController::class, 'index']);
        Route::post('cart/add', [\App\Http\Controllers\CartController::class, 'add']);
        Route::delete('cart/remove/{product_id}', [\App\Http\Controllers\CartController::class, 'remove']);
        Route::patch('cart/update/{product_id}', [\App\Http\Controllers\CartController::class, 'updateQuantity']);

        // Wishlist Routes
        Route::get('wishlist', [\App\Http\Controllers\WishlistController::class, 'index']);
        Route::post('wishlist/add', [\App\Http\Controllers\WishlistController::class, 'add']);
        Route::delete('wishlist/remove/{product_id}', [\App\Http\Controllers\WishlistController::class, 'remove']);

        // Order Routes (own orders)
        Route::get('orders', [\App\Http\Controllers\OrderController::class, 'index']);
        Route::get('orders/{id}', [\App\Http\Controllers\OrderController::class, 'show']);
        Route::post('checkout', [\App\Http\Controllers\OrderController::class, 'checkout']);

        // Health Tip Feedback (authenticated)
        Route::post('health-tips/{id}/feedback', [FeedbackController::class, 'store']);

        // Search (authenticated)
        Route::get('search', [SearchController::class, 'search']);
    });
});

/*
|--------------------------------------------------------------------------
| ADMIN ROUTES (Role-based access control)
|--------------------------------------------------------------------------
*/

Route::group([
    'prefix' => 'admin',
    'middleware' => ['auth:api']
], function () {

    // --- Dashboard (admin only) ---
    Route::get('dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('permission:dashboard.view');

    // --- Site Settings (admin only) ---
    Route::get('site-settings', [AdminDashboardController::class, 'getSettings'])
        ->middleware('permission:site_settings.manage');
    Route::put('site-settings', [AdminDashboardController::class, 'updateSettings'])
        ->middleware('permission:site_settings.manage');

    // --- Activity Logs (admin only) ---
    Route::get('activity-logs', [AdminDashboardController::class, 'activityLogs'])
        ->middleware('permission:activity_logs.view');

    // --- Financial Reports (admin only) ---
    Route::group(['prefix' => 'reports', 'middleware' => 'permission:dashboard.view'], function () {
        Route::get('/overview', [AdminReportController::class, 'index']);
        Route::get('/charts', [AdminReportController::class, 'chartData']);
        Route::get('/losses', [AdminReportController::class, 'lossBreakdown']);
        Route::get('/top-profitable', [AdminReportController::class, 'topProfitableProducts']);
        Route::get('/detailed-ledger', [AdminReportController::class, 'detailedProfitRecords']);
    });

    // --- External Transactions (expenses/income tracking) ---
    Route::group(['prefix' => 'external-transactions', 'middleware' => 'permission:dashboard.view'], function () {
        Route::get('/', [AdminExternalTransactionController::class, 'index']);
        Route::get('/categories', [AdminExternalTransactionController::class, 'categories']);
        Route::post('/', [AdminExternalTransactionController::class, 'store']);
        Route::put('/{id}', [AdminExternalTransactionController::class, 'update']);
        Route::delete('/{id}', [AdminExternalTransactionController::class, 'destroy']);
    });

    // --- Products (admin + staff) ---
    Route::group(['middleware' => 'permission:products.crud'], function () {
        Route::get('products', [AdminProductController::class, 'index']);
        Route::get('products/{id}', [AdminProductController::class, 'show']);
        Route::post('products', [AdminProductController::class, 'store']);
        Route::post('products/bulk', [AdminProductController::class, 'bulkStore']);
        Route::put('products/{id}', [AdminProductController::class, 'update']);
        Route::patch('products/{id}/toggle-publish', [AdminProductController::class, 'togglePublish']);
        Route::delete('products/{id}', [AdminProductController::class, 'destroy']);
        Route::get('products-search', [AdminProductController::class, 'search']);
    });

    // --- Categories (admin + staff) ---
    Route::group(['middleware' => 'permission:categories.crud'], function () {
        Route::get('categories', [AdminCategoryController::class, 'index']);
        Route::post('categories', [AdminCategoryController::class, 'store']);
        Route::put('categories/{id}', [AdminCategoryController::class, 'update']);
        Route::delete('categories/{id}', [AdminCategoryController::class, 'destroy']);
    });

    // --- Units (admin + staff) ---
    Route::group(['middleware' => 'permission:units.crud'], function () {
        Route::get('units', [AdminUnitController::class, 'index']);
        Route::post('units', [AdminUnitController::class, 'store']);
        Route::put('units/{id}', [AdminUnitController::class, 'update']);
        Route::delete('units/{id}', [AdminUnitController::class, 'destroy']);
    });

    // --- Orders (admin + staff) ---
    Route::group(['middleware' => 'permission:orders.manage'], function () {
        Route::get('orders', [AdminOrderController::class, 'index']);
        Route::get('orders/{id}', [AdminOrderController::class, 'show']);
        Route::put('orders/{id}', [AdminOrderController::class, 'update']);
        Route::delete('orders/{id}', [AdminOrderController::class, 'destroy'])
            ->middleware('role:admin'); // Only admin can delete orders
    });

    // --- Inventory (admin + staff) ---
    Route::group(['middleware' => 'permission:inventory.manage'], function () {
        Route::get('suppliers', [AdminSupplierController::class, 'index']);
        Route::post('suppliers', [AdminSupplierController::class, 'store']);
        Route::get('suppliers/{id}', [AdminSupplierController::class, 'show']);
        Route::put('suppliers/{id}', [AdminSupplierController::class, 'update']);
        Route::delete('suppliers/{id}', [AdminSupplierController::class, 'destroy']);

        Route::get('purchases', [AdminInventoryController::class, 'purchaseIndex']);
        Route::post('purchases', [AdminInventoryController::class, 'purchaseStore']);
        Route::get('purchases/{id}', [AdminInventoryController::class, 'purchaseShow']);

        Route::get('inventory/low-stock', [AdminInventoryController::class, 'lowStock']);
        Route::get('inventory/expiring', [AdminInventoryController::class, 'expiringSoon']);
        Route::post('inventory/adjustments', [AdminInventoryController::class, 'adjustment']);
        
        // Expired Items Management
        Route::get('inventory/expired-items', [AdminExpiredItemController::class, 'expired']);
        Route::get('inventory/expiring-soon-configured', [AdminExpiredItemController::class, 'expiringSoon']);
        Route::post('inventory/expired-items/dispose', [AdminExpiredItemController::class, 'dispose']);
        Route::get('inventory/disposals', [AdminExpiredItemController::class, 'disposals']);
    });

    // --- Promotions (admin + staff) ---
    Route::group(['middleware' => 'permission:promotions.crud'], function () {
        Route::get('promotions/products', [AdminPromotionController::class, 'products']);
        Route::get('promotions', [AdminPromotionController::class, 'index']);
        Route::get('promotions/{id}', [AdminPromotionController::class, 'show']);
        Route::post('promotions', [AdminPromotionController::class, 'store']);
        Route::put('promotions/{id}', [AdminPromotionController::class, 'update']);
        Route::delete('promotions/{id}', [AdminPromotionController::class, 'destroy']);
    });

    // --- POS (admin + staff) ---
    Route::get('pos/search', [App\Http\Controllers\Admin\AdminPOSController::class, 'search']);
    Route::post('pos/checkout', [App\Http\Controllers\Admin\AdminPOSController::class, 'checkout']);

    // --- Users (admin only - view & delete, no edit) ---
    Route::group(['middleware' => 'permission:users.view'], function () {
        Route::get('users', [AdminUserController::class, 'index']);
        Route::get('users/{id}', [AdminUserController::class, 'show']);
    });
    Route::delete('users/{id}', [AdminUserController::class, 'destroy'])
        ->middleware('permission:users.delete');

    // --- Staff Management (admin only) ---
    Route::group(['middleware' => 'permission:staff.manage'], function () {
        Route::post('staff', [AdminUserController::class, 'createStaff']);
        Route::put('staff/{id}', [AdminUserController::class, 'updateStaff']);
    });

    // --- Password Reset (admin only) ---
    Route::group(['middleware' => 'permission:password.reset'], function () {
        Route::get('password-reset-requests', [AdminUserController::class, 'passwordResetRequests']);
        Route::post('users/{id}/reset-password', [AdminUserController::class, 'resetPassword']);
    });

    // --- Health Tips (admin + pharmacist) ---
    Route::group(['middleware' => 'permission:health_tips.crud'], function () {
        Route::get('health-tips', [AdminHealthTipController::class, 'index']);
        Route::get('health-tips/{id}', [AdminHealthTipController::class, 'show']);
        Route::post('health-tips', [AdminHealthTipController::class, 'store']);
        Route::put('health-tips/{id}', [AdminHealthTipController::class, 'update']);
        Route::patch('health-tips/{id}/toggle-status', [AdminHealthTipController::class, 'toggleStatus']);
        Route::delete('health-tips/{id}', [AdminHealthTipController::class, 'destroy']);
    });

    // --- Roles & Permissions (admin only) ---
    Route::group(['middleware' => 'role:admin'], function () {
        Route::get('roles', [AdminRoleController::class, 'index']);
        Route::post('roles', [AdminRoleController::class, 'store']);
        Route::put('roles/{id}', [AdminRoleController::class, 'update']);
        Route::delete('roles/{id}', [AdminRoleController::class, 'destroy']);
        Route::get('permissions', [AdminRoleController::class, 'permissions']);
    });

    // --- Contact Messages (admin only) ---
    Route::get('contact-messages', [ContactUsController::class, 'index'])
        ->middleware('role:admin');
    Route::put('contact-messages/{id}', [ContactUsController::class, 'update'])
        ->middleware('role:admin');
});

<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminCategoryController;
use App\Http\Controllers\Admin\AdminNotificationLogController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminStoreVerificationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Home\HomeCategoryController;
use App\Http\Controllers\Home\HomeLatestProductController;
use App\Http\Controllers\Home\HomePopularProductController;
use App\Http\Controllers\Home\HomeProductSearchController;
use App\Http\Controllers\Home\HomeStoreController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Seller\SellerProductController;
use App\Http\Controllers\Seller\SellerProductVariantController;
use App\Http\Controllers\Seller\SellerOrderController;
use App\Http\Controllers\Seller\SellerDashboardController;
use App\Http\Controllers\Seller\SellerStoreController;
use App\Http\Controllers\Customer\CustomerCartController;
use App\Http\Controllers\Customer\CustomerDashboardController;
use App\Http\Controllers\Customer\CustomerOrderController;
use App\Http\Controllers\Customer\CustomerProductReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Debug endpoint to test connectivity
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'API is reachable',
        'timestamp' => now(),
        'headers' => request()->headers->all()
    ]);
});

// Public routes
Route::post('/register/customer', [AuthController::class, 'registerCustomer']);
Route::post('/register/seller',   [AuthController::class, 'registerSeller']);
Route::post('/login',             [AuthController::class, 'login']);
Route::get('/verify-email',       [AuthController::class, 'verifyEmail']);
Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
Route::post('/forgot-password',     [AuthController::class, 'forgotPassword']);
Route::post('/reset-password',      [AuthController::class, 'resetPassword']);

// Public product routes
Route::get('/products/search', [HomeProductSearchController::class, 'index']);
Route::get('/products/{uuid}/similar', [HomeProductSearchController::class, 'similar'])->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
Route::get('/products/{uuid}', [HomeProductSearchController::class, 'show'])->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
Route::get('/stores/{id}', [HomeStoreController::class, 'show'])->whereNumber('id');

Route::get('/categories', [HomeCategoryController::class, 'index']);
Route::get('/home/latest-products', [HomeLatestProductController::class, 'index']);
Route::get('/home/popular-products', [HomePopularProductController::class, 'index']);

// Authenticated routes
Route::middleware('auth:api')->group(function () {

    // Profile routes (all roles)
    Route::get('/profile',          [ProfileController::class, 'show']);
    Route::post('/profile/info',    [ProfileController::class, 'updateInfo']);
    Route::post('/profile/address', [ProfileController::class, 'updateAddress']);
    Route::post('/profile/store',   [ProfileController::class, 'updateStore']);
    Route::post('/profile/password',[ProfileController::class, 'changePassword']);

    // Notification routes (all roles)
    Route::get('/notifications',                    [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',        [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read',      [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/push-subscription',  [NotificationController::class, 'savePushSubscription']);
    Route::delete('/notifications/push-subscription',[NotificationController::class, 'deletePushSubscription']);
    Route::post('/notifications/{id}/mark-read',     [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}',             [NotificationController::class, 'destroy']);

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard',     [AdminDashboardController::class, 'index']);
        Route::get('/users',         [AdminUserController::class, 'index']);
        Route::get('/users/{id}',    [AdminUserController::class, 'show']);
        Route::post('/users',        [AdminUserController::class, 'store']);
        Route::post('/users/{id}',   [AdminUserController::class, 'update']);
        Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

        Route::get('/categories',         [AdminCategoryController::class, 'index']);
        Route::get('/categories/{id}',    [AdminCategoryController::class, 'show']);
        Route::post('/categories',        [AdminCategoryController::class, 'store']);
        Route::put('/categories/{id}',    [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [AdminCategoryController::class, 'destroy']);

        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/{uuid}', [AdminProductController::class, 'show'])->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
        Route::get('/logs', [AdminNotificationLogController::class, 'index']);

        Route::get('/store-verifications',                      [AdminStoreVerificationController::class, 'index']);
        Route::get('/store-verifications/{store}',         [AdminStoreVerificationController::class, 'show']);
        Route::get('/store-verifications/{store}/logs',    [AdminStoreVerificationController::class, 'logs']);
        Route::post('/store-verifications/{store}/approve', [AdminStoreVerificationController::class, 'approve']);
        Route::post('/store-verifications/{store}/reject',  [AdminStoreVerificationController::class, 'reject']);
        Route::post('/store-verifications/{store}/pending', [AdminStoreVerificationController::class, 'pending']);
        Route::get('/store-verification-logs',              [AdminStoreVerificationController::class, 'allLogs']);
        Route::post('/store-verification-logs/{logId}/revert', [AdminStoreVerificationController::class, 'revertLog']);
    });

    // Seller routes
    Route::middleware('role:seller')->prefix('seller')->group(function () {
        // Always accessible (no store verification needed)
        Route::get('/dashboard', [SellerDashboardController::class, 'index']);
        Route::get('/store-status', [SellerStoreController::class, 'status']);
        Route::post('/resubmit-store', [SellerStoreController::class, 'resubmit']);

        // Only accessible when store is verified
        Route::middleware('store.verified')->group(function () {
            Route::get('/products', [SellerProductController::class, 'index']);
            Route::get('/products/{uuid}', [SellerProductController::class, 'show'])->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
            Route::post('/products', [SellerProductController::class, 'store']);
            Route::put('/products/{uuid}', [SellerProductController::class, 'update'])->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
            Route::delete('/products/{uuid}', [SellerProductController::class, 'destroy'])->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

            // Product variants routes
            Route::get('/products/{product_uuid}/variants', [SellerProductVariantController::class, 'index'])->where('product_uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
            Route::get('/products/{product_uuid}/variants/{variant_id}', [SellerProductVariantController::class, 'show'])->where('product_uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
            Route::post('/products/{product_uuid}/variants', [SellerProductVariantController::class, 'store'])->where('product_uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
            Route::put('/products/{product_uuid}/variants/{variant_id}', [SellerProductVariantController::class, 'update'])->where('product_uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
            Route::delete('/products/{product_uuid}/variants/{variant_id}', [SellerProductVariantController::class, 'destroy'])->where('product_uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

            // Item-level orders for this seller's store
            Route::get('/order-items', [SellerOrderController::class, 'index']);
            Route::get('/order-items/{item}', [SellerOrderController::class, 'show']);
            Route::put('/order-items/{item}/status', [SellerOrderController::class, 'updateStatus']);
            Route::put('/order-items/{item}/shipment', [SellerOrderController::class, 'updateShipment']);
            Route::put('/order-items/{item}/cancel', [SellerOrderController::class, 'cancelItem']);
        });
    });



    // Customer routes
    Route::middleware('role:customer')->prefix('customer')->group(function () {
        Route::get('/dashboard', [CustomerDashboardController::class, 'index']);

        // Cart routes
        Route::get('/cart', [CustomerCartController::class, 'index']);
        Route::post('/cart', [CustomerCartController::class, 'store']);
        Route::put('/cart/{id}', [CustomerCartController::class, 'update']);
        Route::delete('/cart/{id}', [CustomerCartController::class, 'destroy']);
        Route::delete('/cart', [CustomerCartController::class, 'destroyAll']);

        // Item-level order routes
        Route::get('/order-items', [CustomerOrderController::class, 'index']);
        Route::get('/order-items/{item}', [CustomerOrderController::class, 'show']);
        Route::post('/order-items', [CustomerOrderController::class, 'store']);
        Route::post('/order-items/calculate-shipping', [CustomerOrderController::class, 'calculateShipping']);
        Route::put('/order-items/{item}/cancel', [CustomerOrderController::class, 'cancelItem']);
        Route::put('/order-items/{item}/delivered', [CustomerOrderController::class, 'deliverItem']);
        Route::post('/order-items/{item}/review', [CustomerProductReviewController::class, 'store']);
    });
});

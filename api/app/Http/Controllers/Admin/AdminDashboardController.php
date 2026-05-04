<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

class AdminDashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard
     * Summary metrics and recent activity for the admin dashboard.
     */
    public function index()
    {
        $managedUsers = User::query()->where('id', '!=', 1);
        $stores = Store::query();
        $activeProducts = Product::query()->where('status', 'active');

        $pendingStoresQuery = Store::query()
            ->where(function ($query) {
                $query->whereDoesntHave('verification')
                    ->orWhereHas('verification', function ($verificationQuery) {
                        $verificationQuery->where('store_status', 'pending');
                    });
            });

        $recentUsers = User::query()
            ->where('id', '!=', 1)
            ->latest()
            ->take(5)
            ->get([
                'id',
                'uuid',
                'firstname',
                'lastname',
                'email',
                'role',
                'email_verified_at',
                'created_at',
            ]);

        $recentProducts = Product::query()
            ->where('status', 'active')
            ->with(['category:id,name', 'store:id,uuid,store_name', 'variants:id,product_id,price,stock'])
            ->latest()
            ->take(5)
            ->get([
                'id',
                'uuid',
                'store_id',
                'name',
                'category_id',
                'status',
                'created_at',
            ]);

        $pendingStores = $pendingStoresQuery
            ->with([
                'user:id,firstname,lastname,email',
                'category:id,name',
                'verification:id,store_id,store_status,rejection_reason,reviewed_at',
            ])
            ->latest()
            ->take(5)
            ->get([
                'id',
                'uuid',
                'user_id',
                'store_name',
                'category_id',
                'verified_at',
                'created_at',
            ]);

        return response()->json([
            'stats' => [
                'managed_users' => $managedUsers->count(),
                'customers' => (clone $managedUsers)->where('role', 'customer')->count(),
                'sellers' => (clone $managedUsers)->where('role', 'seller')->count(),
                'verified_users' => (clone $managedUsers)->whereNotNull('email_verified_at')->count(),
                'active_products' => $activeProducts->count(),
                'categories' => Category::query()->count(),
                'stores' => $stores->count(),
                'verified_stores' => (clone $stores)->whereNotNull('verified_at')->count(),
                'pending_stores' => (clone $pendingStoresQuery)->count(),
                'rejected_stores' => Store::query()
                    ->whereHas('verification', function ($query) {
                        $query->where('store_status', 'rejected');
                    })
                    ->count(),
            ],
            'recent_users' => $recentUsers,
            'recent_products' => $recentProducts,
            'pending_stores' => $pendingStores,
        ]);
    }
}

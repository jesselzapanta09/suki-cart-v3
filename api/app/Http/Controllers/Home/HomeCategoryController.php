<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Category;

class HomeCategoryController extends Controller
{
    public function index()
    {
        return Category::query()
            ->where('status', 1)
            ->withCount([
                'products' => function ($query) {
                    $query->where('status', 'active')
                        ->whereHas('variants', function ($variantQuery) {
                            $variantQuery->where('stock', '>', 0);
                        })
                        ->whereHas('store.verification', function ($verificationQuery) {
                            $verificationQuery->where('store_status', 'approved');
                        });
                },
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'description']);
    }
}

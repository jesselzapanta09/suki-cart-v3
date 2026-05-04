<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreProductReviewRequest;
use App\Models\OrderItem;
use App\Models\ProductReview;
use Illuminate\Support\Facades\DB;

class CustomerProductReviewController extends Controller
{
    public function store(StoreProductReviewRequest $request, int $itemId)
    {
        $item = OrderItem::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $itemId)
            ->with(['product', 'variant', 'review'])
            ->firstOrFail();

        if ($item->status !== 'delivered') {
            return response()->json([
                'message' => 'Cannot review product.',
                'error' => 'Only delivered items can be reviewed.',
            ], 422);
        }

        if ($item->review) {
            return response()->json([
                'message' => 'Review already exists.',
                'error' => 'You have already reviewed this order item.',
            ], 422);
        }

        $validated = $request->validated();

        $review = DB::transaction(function () use ($request, $item, $validated) {
            return ProductReview::create([
                'user_id' => $request->user()->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'rating' => $validated['rating'],
                'variant_name' => $item->variant?->name ?? '',
                'review' => trim($validated['review']),
            ]);
        });

        return response()->json([
            'message' => 'Product review submitted successfully.',
            'data' => $review,
        ], 201);
    }
}

<?php

namespace App\Http\Controllers\Seller;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\StoreVerification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SellerStoreController extends Controller
{
    /**
     * GET /api/seller/store-status
     * Return the current store verification status for the authenticated seller.
     */
    public function status(Request $request)
    {
        $user = $request->user();
        $store = $user->store;

        if (!$store) {
            return response()->json([
                'store_status' => 'none',
                'message' => 'No store found.',
            ]);
        }

        $store->load(['verification', 'category']);

        $verification = $store->verification;

        return response()->json([
            'store_status' => $verification->store_status ?? 'pending',
            'rejection_reason' => $verification->rejection_reason ?? null,
            'reviewed_at' => $verification->reviewed_at ?? null,
            'store' => $store,
        ]);
    }

    /**
     * POST /api/seller/resubmit-store
     * Resubmit a rejected store for review — sets status back to pending.
     */
    public function resubmit(Request $request)
    {
        $user = $request->user();
        $store = $user->store;

        if (!$store) {
            return response()->json(['message' => 'No store found.'], 404);
        }

        $verification = $store->verification;

        if (!$verification) {
            return response()->json(['message' => 'Store is already pending.'], 422);
        }

        if ($verification->store_status !== 'rejected') {
            return response()->json(['message' => 'Only rejected stores can be resubmitted.'], 422);
        }

        $verification->update([
            'store_status' => 'pending',
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        User::where('role', 'admin')->each(function (User $admin) use ($store) {

            SendNotificationJob::dispatch(
                (int) $admin->id,
                'store',
                'Store Resubmitted for Review',
                "The store '{$store->store_name}' has been resubmitted for review.",
                [
                    'store_uuid' => (string) $store->uuid,
                    'store_name' => (string) $store->store_name,
                    'url' => '/admin/seller-verify/' . $store->uuid,
                ]
            );

            Log::info('ADMIN CHECK', [
                'admin_id' => $admin->id,
                'has_token' => \App\Models\PushSubscription::where('user_id', $admin->id)->exists()
            ]);

        });

        return response()->json([
            'message' => 'Store resubmitted for review.',
            'store_status' => 'pending',
        ]);
    }
}

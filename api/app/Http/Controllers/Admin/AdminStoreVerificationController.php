<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Store;
use App\Models\StoreVerification;
use App\Models\StoreVerificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminStoreVerificationController extends Controller
{
    /**
     * GET /api/admin/store-verifications
     * Paginated list of stores with their verification status.
     */
    public function index(Request $request)
    {
        $query = Store::with(['user', 'category', 'verification', 'latestVerificationLog.performer']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        // Status filter
        if ($status = $request->input('status')) {
            if ($status === 'pending') {
                // Include stores with no verification record (implicitly pending)
                // AND stores with a verification record where status = 'pending'
                $query->where(function ($q) {
                    $q->whereDoesntHave('verification')
                        ->orWhereHas('verification', function ($vq) {
                            $vq->where('store_status', 'pending');
                        });
                });
            } else {
                $query->whereHas('verification', function ($q) use ($status) {
                    $q->where('store_status', $status);
                });
            }
        }

        // Sort
        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'store_name', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'ascend' ? 'asc' : 'desc');
        }

        $perPage = (int) $request->input('per_page', 10);
        $stores = $query->paginate($perPage);

        return response()->json($stores);
    }

    /**
     * GET /api/admin/store-verifications/{store}
     * Route model binding automatically resolves UUID to Store
     */
    public function show(Store $store)
    {
        $store->load(['user.locations', 'category', 'verification.reviewer']);
        return response()->json(['store' => $store]);
    }

    /**
     * POST /api/admin/store-verifications/{store}/approve
     * Route model binding automatically resolves UUID to Store
     */
    public function approve(Store $store)
    {
        $verification = $store->verification;
        $previousStatus = $verification?->store_status ?? null;

        if (!$verification) {
            $verification = StoreVerification::create([
                'store_id' => $store->id,
                'store_status' => 'approved',
                'reviewed_by' => Auth::user()->uuid,
                'reviewed_at' => now(),
            ]);
        } else {
            $verification->update([
                'store_status' => 'approved',
                'rejection_reason' => null,
                'reviewed_by' => Auth::user()->uuid,
                'reviewed_at' => now(),
            ]);
        }

        $store->update(['verified_at' => now()]);
        $this->logAction($store->id, 'approve', $previousStatus, 'approved');

        SendNotificationJob::dispatch(
            (int) $store->user_id,
            'store',
            'Store Approved',
            "Congratulations! Your store '{$store->store_name}' has been approved. You can now start selling.",
            [
                'store_uuid' => (string) $store->uuid,
                'status' => 'approved',
                'url' => '/seller/dashboard',
            ]
        );

        

        return response()->json(['message' => 'Store approved successfully.', 'store' => $store->load(['verification', 'user', 'category'])]);
    }

    /**
     * POST /api/admin/store-verifications/{store}/reject
     * Route model binding automatically resolves UUID to Store
     */
    public function reject(Request $request, Store $store)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);
        $verification = $store->verification;
        $previousStatus = $verification?->store_status ?? null;

        if (!$verification) {
            $verification = StoreVerification::create([
                'store_id' => $store->id,
                'store_status' => 'rejected',
                'rejection_reason' => $request->input('rejection_reason'),
                'reviewed_by' => Auth::user()->uuid,
                'reviewed_at' => now(),
            ]);
        } else {
            $verification->update([
                'store_status' => 'rejected',
                'rejection_reason' => $request->input('rejection_reason'),
                'reviewed_by' => Auth::user()->uuid,
                'reviewed_at' => now(),
            ]);
        }

        $store->update(['verified_at' => null]);
        $this->logAction($store->id, 'reject', $previousStatus, 'rejected', $request->input('rejection_reason'));

        $hasToken = \App\Models\PushSubscription::where('user_id', $store->user_id)->exists();

        if (!$hasToken) {
            Log::warning('User has no push token', ['user_id' => $store->user_id]);
        }

        SendNotificationJob::dispatch(
            (int) $store->user_id,
            'store',
            'Store Rejected',
            "Your store '{$store->store_name}' has been rejected. Reason: {$request->input('rejection_reason')}",
            [
                'store_uuid' => (string) $store->uuid,
                'status' => 'rejected',
                'rejection_reason' => (string) $request->input('rejection_reason'),
                'url' => "/seller/dashboard"
            ]
        );

        return response()->json(['message' => 'Store rejected.', 'store' => $store->load(['verification', 'user', 'category'])]);
    }

    /**
     * POST /api/admin/store-verifications/{store}/pending
     * Route model binding automatically resolves UUID to Store
     */
    public function pending(Store $store)
    {
        $verification = $store->verification;

        if (!$verification) {
            return response()->json(['message' => 'Store is already pending.'], 422);
        }

        $previousStatus = $verification->store_status;

        $verification->update([
            'store_status' => 'pending',
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $store->update(['verified_at' => null]);
        $this->logAction($store->id, 'pending', $previousStatus, 'pending');


        SendNotificationJob::dispatch(
            (int) $store->user_id,
            'store',
            'Store Under Re-review',
            "Your store '{$store->store_name}' has been placed back under review by an admin.",
            [
                'store_uuid' => (string) $store->uuid,
                'status' => 'pending',
                'url' => '/seller/dashboard',
            ]
        );

        

        return response()->json(['message' => 'Store set back to pending.', 'store' => $store->load(['verification', 'user', 'category'])]);
    }

    /**
     * GET /api/admin/store-verification-logs
     * Latest log per store only, newest first, paginated.
     */
    public function allLogs(Request $request)
    {
        // Get the max log ID per store (= the latest action per store)
        $latestIds = StoreVerificationLog::selectRaw('MAX(id) as id')
            ->groupBy('store_id')
            ->pluck('id');

        $query = StoreVerificationLog::with(['store.category', 'performer'])
            ->whereIn('id', $latestIds)
            ->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->whereHas('store', function ($q) use ($search) {
                $q->where('store_name', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        return response()->json($query->paginate($perPage));
    }

    /**
     * GET /api/admin/store-verifications/{store}/logs
     * Route model binding automatically resolves UUID to Store
     */
    public function logs(Store $store)
    {
        $store->load(['verification', 'category']);

        $logs = StoreVerificationLog::with('performer')
            ->where('store_id', $store->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'store' => $store,
            'logs'  => $logs,
        ]);
    }

    /**
     * POST /api/admin/store-verification-logs/{logId}/revert
     */
    public function revertLog($logId)
    {
        $log = StoreVerificationLog::findOrFail($logId);
        $store = Store::findOrFail($log->store_id);
        $verification = $store->verification;

        $currentStatus = $verification?->store_status ?? 'pending';
        $targetStatus  = $log->previous_status ?? 'pending';

        if ($targetStatus === 'approved') {
            if (!$verification) {
                StoreVerification::create([
                    'store_id'    => $store->id,
                    'store_status' => 'approved',
                    'reviewed_by' => Auth::user()->uuid,
                    'reviewed_at' => now(),
                ]);
            } else {
                $verification->update([
                    'store_status'     => 'approved',
                    'rejection_reason' => null,
                    'reviewed_by'      => Auth::user()->uuid,
                    'reviewed_at'      => now(),
                ]);
            }
            $store->update(['verified_at' => now()]);
        } elseif ($targetStatus === 'rejected') {
            if (!$verification) {
                StoreVerification::create([
                    'store_id'         => $store->id,
                    'store_status'     => 'rejected',
                    'rejection_reason' => $log->rejection_reason,
                    'reviewed_by'      => Auth::user()->uuid,
                    'reviewed_at'      => now(),
                ]);
            } else {
                $verification->update([
                    'store_status'     => 'rejected',
                    'rejection_reason' => $log->rejection_reason,
                    'reviewed_by'      => Auth::user()->uuid,
                    'reviewed_at'      => now(),
                ]);
            }
            $store->update(['verified_at' => null]);
        } else {
            if ($verification) {
                $verification->update([
                    'store_status'     => 'pending',
                    'rejection_reason' => null,
                    'reviewed_by'      => null,
                    'reviewed_at'      => null,
                ]);
            }
            $store->update(['verified_at' => null]);
        }

        $this->logAction(
            $store->id,
            'revert',
            $currentStatus,
            $targetStatus,
            $targetStatus === 'rejected' ? $log->rejection_reason : null
        );

        return response()->json([
            'message' => "Store reverted to {$targetStatus}.",
            'store'   => $store->load(['verification', 'user', 'category']),
        ]);
    }

    /**
     * Helper: write a log entry.
     */
    private function logAction(int $storeId, string $action, ?string $previousStatus, string $newStatus, ?string $rejectionReason = null): void
    {
        StoreVerificationLog::create([
            'store_id'         => $storeId,
            'action'           => $action,
            'previous_status'  => $previousStatus,
            'new_status'       => $newStatus,
            'rejection_reason' => $rejectionReason,
            'performed_by'     => Auth::user()->uuid,
        ]);
    }
}

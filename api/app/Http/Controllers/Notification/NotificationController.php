<?php
namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * List the authenticated user's notifications (paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $notifications = Notification::where('user_id', Auth::id())
            ->latest()
            ->paginate($perPage);

        return response()->json($notifications);
    }

    /**
     * Unread count for the notification bell badge.
     */
    public function unreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(int $id): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read.']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): JsonResponse
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    /**
     * Delete a single notification.
     */
    public function destroy(int $id): JsonResponse
    {
        Notification::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail()
            ->delete();

        return response()->json(['message' => 'Notification deleted.']);
    }

    // ── Push subscription management (FCM) ──────────────────────────────

    /**
     * Save an FCM device token.
     */
    public function savePushSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string|max:500',
            'device_type' => 'nullable|in:web,android,ios',
            'device_name' => 'nullable|string|max:255',
        ]);

        PushSubscription::updateOrCreate(
            ['device_token' => $request->device_token],
            [
                'user_id' => Auth::id(),
                'device_type' => $request->device_type ?? 'web',
                'device_name' => $request->device_name,
                'last_used_at' => now(),
            ]
        );

        return response()->json(['message' => 'Push subscription saved.'], 201);
    }

    /**
     * Remove a push subscription by device token.
     */
    public function deletePushSubscription(Request $request): JsonResponse
    {
        $request->validate(['device_token' => 'required|string']);

        PushSubscription::where('user_id', Auth::id())
            ->where('device_token', $request->device_token)
            ->delete();

        return response()->json(['message' => 'Push subscription removed.']);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class AdminNotificationLogController extends Controller
{
    /**
     * GET /api/admin/logs
     * Admin-only activity feed backed by notifications.
     */
    public function index(Request $request)
    {
        $query = Notification::query()
            ->with(['user:id,firstname,lastname,email,role'])
            ->latest();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('role', 'like', "%{$search}%");
                    });
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json($query->paginate($perPage));
    }
}

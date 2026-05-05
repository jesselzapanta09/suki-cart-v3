<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\PushSubscription;
use App\Services\FCMService;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    public static function send(int $userId, string $type, string $title, string $message, ?array $data = null): Notification
    {
        $notification = Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        static::dispatchFCM(
            $userId,
            $title,
            $message,
            $type,
            array_merge($data ?? [], [
                'notification_id' => $notification->id,
            ])
        );

        return $notification;
    }

    public static function dispatchFCM(
        int $userId,
        string $title,
        string $message,
        string $type = 'system',
        ?array $data = null
    ): void {
        $fcmService = new FCMService();

        if (!$fcmService->isConfigured()) {
            Log::warning('[Notification] FCM is not configured');
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            Log::info('[Notification] No push subscriptions found for user', [
                'user_id' => $userId,
            ]);
            return;
        }

        $pushData = array_map('strval', array_merge($data ?? [], [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]));

        $results = [];

        foreach ($subscriptions as $subscription) {
            $token = $subscription->device_token;

            try {
                $success = $fcmService->sendNotification(
                    $token,
                    $title,
                    $message,
                    $pushData
                );

                if (!$success) {
                    Log::warning('[FCM] Retry sending', [
                        'token' => $token,
                    ]);

                    $success = $fcmService->sendNotification(
                        $token,
                        $title,
                        $message,
                        $pushData
                    );
                }

                if (!$success) {
                    Log::warning('[FCM] Delivery failed after retry', [
                        'user_id' => $userId,
                        'token' => $token,
                    ]);
                }

                $results[] = [
                    'token' => $token,
                    'success' => $success,
                ];
            } catch (\Throwable $e) {
                Log::error('[FCM] Send exception', [
                    'user_id' => $userId,
                    'token' => $token,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'token' => $token,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info('[Notification] FCM dispatch completed', [
            'user_id' => $userId,
            'total_tokens' => $subscriptions->count(),
            'results' => $results,
        ]);
    }
}

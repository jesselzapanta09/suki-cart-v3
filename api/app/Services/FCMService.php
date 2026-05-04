<?php

namespace App\Services;

use App\Models\PushSubscription;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FCMService
{
    private string $projectId;
    private string $serviceAccountJson;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;

    public function __construct()
    {
        $this->projectId = config('app.fcm_project_id', '');
        $this->serviceAccountJson = config('app.fcm_service_account_json', '');

        if (!$this->projectId || !$this->serviceAccountJson) {
            Log::warning('[FCM] Missing configuration');
        }

        Log::info('FCM CHECK', [
            'project' => $this->projectId,
            'has_json' => !empty($this->serviceAccountJson),
        ]);
    }

    public function sendNotification(
        string $deviceToken,
        string $title,
        string $message,
        ?array $data = null
    ): bool {
        try {
            $accessToken = $this->getAccessToken();

            if (!$accessToken) {
                Log::error('[FCM] No access token');
                return false;
            }

            // 🔥 CLEAN + CORRECT PAYLOAD
            $payload = [
                'message' => [
                    'token' => $deviceToken,

                    // REQUIRED for browser notifications
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                    ],

                    // MUST be string values
                    'data' => array_map('strval', $data ?? []),

                    // Web push config
                    'webpush' => [
                        'notification' => [
                            'icon' => '/suki-cart-logo.png',
                            'badge' => '/suki-cart-logo.png',
                        ],
                        'fcm_options' => [
                            'link' => 'http://localhost:3000/notifications',
                        ],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)->post(
                "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send",
                $payload
            );

            if (!$response->successful()) {
                Log::error('[FCM] Send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                // delete invalid tokens
                if (in_array($response->status(), [401, 404])) {
                    PushSubscription::where('device_token', $deviceToken)->delete();
                }

                return false;
            }

            return true;

        } catch (Throwable $e) {
            Log::error('[FCM] Exception', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendNotificationsToMultiple(array $tokens, string $title, string $message, ?array $data = null): array
{
    $results = [];

    foreach ($tokens as $token) {
        $success = $this->sendNotification($token, $title, $message, $data);
        $results[] = [
            'token' => $token,
            'success' => $success,
        ];
    }

    return $results;
}

    private function getAccessToken(): ?string
{
    if ($this->accessToken && time() < $this->tokenExpiry) {
        return $this->accessToken;
    }

    try {
        $credentialsArray = json_decode($this->serviceAccountJson, true);

        if (!$credentialsArray) {
            \Log::error('[FCM] Invalid JSON');
            return null;
        }

        $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials(
            'https://www.googleapis.com/auth/firebase.messaging',
            $credentialsArray
        );

        $tokenData = $credentials->fetchAuthToken();

        $this->accessToken = $tokenData['access_token'] ?? null;
        $this->tokenExpiry = time() + 3500;

        return $this->accessToken;

    } catch (\Throwable $e) {
        \Log::error('[FCM] Token error', [
            'error' => $e->getMessage(),
        ]);
        return null;
    }
}

    public function isConfigured(): bool
    {
        return !empty($this->projectId) && !empty($this->serviceAccountJson);
    }
}
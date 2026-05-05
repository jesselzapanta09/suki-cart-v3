<?php

namespace App\Services;

use App\Models\PushSubscription;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FCMService
{
    private string $projectId;
    private string $serviceAccountJson;
    private string $frontendUrl;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;

    public function __construct()
    {
        $this->projectId = config('app.fcm_project_id', '');
        $this->serviceAccountJson = config('app.fcm_service_account_json', '');
        $this->frontendUrl = rtrim((string) config('app.frontend_url', 'http://localhost:3000'), '/');

        if (!$this->projectId || !$this->serviceAccountJson) {
            Log::warning('[FCM] Missing configuration');
        }

        Log::info('[FCM] Configuration check', [
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

            $stringData = $this->normalizeData($data, $title, $message);
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                    ],
                    'data' => $stringData,
                    'webpush' => [
                        'notification' => [
                            'icon' => '/suki-cart-logo.png',
                            'badge' => '/suki-cart-logo.png',
                        ],
                        'fcm_options' => [
                            'link' => $this->buildFrontendUrl($stringData['url'] ?? null),
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

                if ($this->shouldDeleteToken($response)) {
                    PushSubscription::where('device_token', $deviceToken)->delete();
                    Log::warning('[FCM] Deleted invalid token', [
                        'token' => $deviceToken,
                    ]);
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

    private function normalizeData(?array $data, string $title, string $message): array
    {
        $merged = array_merge($data ?? [], [
            'title' => $title,
            'message' => $message,
            'notification_foreground' => 'true',
        ]);

        return array_map(static fn ($value) => (string) $value, $merged);
    }

    private function buildFrontendUrl(?string $relativePath = null): string
    {
        if (!$relativePath) {
            return "{$this->frontendUrl}/";
        }

        if (preg_match('/^https?:\/\//i', $relativePath)) {
            return $relativePath;
        }

        return "{$this->frontendUrl}/" . ltrim($relativePath, '/');
    }

    private function shouldDeleteToken(Response $response): bool
    {
        $status = $response->status();
        if ($status < 400 || $status >= 500) {
            return false;
        }

        $payload = $response->json();
        $error = $payload['error'] ?? [];
        $fcmStatus = (string) ($error['status'] ?? '');
        $message = (string) ($error['message'] ?? '');

        if (in_array($fcmStatus, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            return true;
        }

        if (str_contains($message, 'registration token is not a valid FCM registration token')) {
            return true;
        }

        if (str_contains($message, 'Requested entity was not found')) {
            return true;
        }

        return false;
    }

    private function getAccessToken(): ?string
    {
        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        try {
            $credentialsArray = json_decode($this->serviceAccountJson, true);

            if (!$credentialsArray) {
                Log::error('[FCM] Invalid JSON');
                return null;
            }

            $credentials = new ServiceAccountCredentials(
                'https://www.googleapis.com/auth/firebase.messaging',
                $credentialsArray
            );

            $tokenData = $credentials->fetchAuthToken();

            $this->accessToken = $tokenData['access_token'] ?? null;
            $this->tokenExpiry = time() + 3500;

            return $this->accessToken;
        } catch (Throwable $e) {
            Log::error('[FCM] Token error', [
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

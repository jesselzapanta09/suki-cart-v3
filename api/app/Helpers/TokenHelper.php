<?php

namespace App\Helpers;

use App\Models\UserToken;

class TokenHelper
{
    /**
     * Create a secure random token for a user, replacing any existing token of the same type.
     *
     * @param  int     $userId
     * @param  string  $type            e.g. 'email_verify', 'password_reset'
     * @param  int     $expiresInMinutes
     * @return string                   The raw token string
     */
    public static function create(int $userId, string $type, int $expiresInMinutes = 5): string
    {
        $token     = bin2hex(random_bytes(32));
        $expiresAt = now()->addMinutes($expiresInMinutes);

        // Remove any existing token of the same type for this user
        UserToken::where('user_id', $userId)->where('type', $type)->delete();

        UserToken::create([
            'user_id'    => $userId,
            'token'      => $token,
            'type'       => $type,
            'expires_at' => $expiresAt,
        ]);

        return $token;
    }

    /**
     * Find a valid (non-expired) token record.
     *
     * @return \App\Models\UserToken|null
     */
    public static function find(string $token, string $type): ?UserToken
    {
        return UserToken::where('token', $token)
            ->where('type', $type)
            ->first();
    }

    /**
     * Check whether a UserToken record is expired.
     */
    public static function isExpired(UserToken $userToken): bool
    {
        return now()->gt($userToken->expires_at);
    }

    /**
     * Consume (delete) a token after it has been used.
     */
    public static function consume(UserToken $userToken): void
    {
        $userToken->delete();
    }
}

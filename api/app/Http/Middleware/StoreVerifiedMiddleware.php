<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StoreVerifiedMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->role !== 'seller') {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $store = $user->store;

        if (!$store) {
            return response()->json([
                'message' => 'Store not found.',
                'store_status' => 'none',
            ], 403);
        }

        $verification = $store->verification;

        if (!$verification || $verification->store_status !== 'approved') {
            $status = $verification->store_status ?? 'pending';

            return response()->json([
                'message' => 'Your store is not yet verified.',
                'store_status' => $status,
                'rejection_reason' => $verification->rejection_reason ?? null,
            ], 403);
        }

        return $next($request);
    }
}

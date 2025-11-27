<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateServiceRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('graph_builder.api_token');

        // If no token configured, reject (fail-safe)
        if (empty($expectedToken)) {
            return response()->json([
                'message' => 'Service authentication not configured',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Extract token from Authorization header
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix

        // Use hash_equals for constant-time comparison (prevents timing attacks)
        if (! hash_equals($expectedToken, $token)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\JsonResponse)  $next
     */
    public function handle(Request $request, Closure $next): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->email_verified_at == null) {
            return new JsonResponse([
                'success' => false,
                'message' => "Email is not verified",
            ], 409);
        }

        return $next($request);
    }
}

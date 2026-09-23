<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->attributes->get('authUser');

        if (! $user) {
            return response()->json([
                'message' => 'Not authenticated.',
            ], 401);
        }

        if (! in_array($user->role ?? null, $allowedRoles, true)) {
            return $this->forbiddenResponse();
        }

        return $next($request);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'You are not authorized to perform this action.',
        ], 403);
    }
}

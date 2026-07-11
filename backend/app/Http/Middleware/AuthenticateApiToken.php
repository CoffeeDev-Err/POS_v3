<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Support both standard bearer auth and the legacy custom header used by older clients.
        $token = $request->bearerToken() ?: $request->header('X-Auth-Token');
        if (!$token) {
            return $this->unauthorizedResponse();
        }

        $user = DB::table('users')
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        if (!$user || !$user->active) {
            return $this->unauthorizedResponse();
        }

        // Downstream controllers can read this without repeating another database lookup.
        $request->attributes->set('authUser', $user);

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Not authenticated.',
        ], 401);
    }
}

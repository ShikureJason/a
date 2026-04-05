<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\TokenService;

class UserAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token required'], 401);
        }

        $data = app(TokenService::class)->validate($token);

        if (!$data) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        // inject
        $request->merge([
            'auth_user_id' => $data['user_id'],
            'auth_role' => $data['role']
        ]);

        return $next($request);
    }
}
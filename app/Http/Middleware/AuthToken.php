<?php
namespace App\Http\Middleware;

use Closure;
use App\Services\TokenService;
use Illuminate\Support\Facades\Redis;

class AuthToken
{
    public function __construct(private TokenService $tokenService) {}

   
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token required'], 401);
        }

        $data = $this->tokenService->validate($token);

        if (!$data) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        // inject user
        $request->merge([
            'auth_user_id' => $data['user_id'],
            'auth_role' => $data['role']
        ]);

        // 🔥 update online (สำคัญ)
        $now = time();
        Redis::zadd('online_users', $now, $data['user_id']);

        return $next($request);
    }
}
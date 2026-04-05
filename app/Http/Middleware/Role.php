<?php
namespace App\Http\Middleware;

use Closure;

class Role
{
    public function handle($request, Closure $next, ...$roles)
    {
        $userRole = $request->get('auth_role');

        if (!$userRole || !in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }

        return $next($request);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Services\TokenService;

class AuthUser extends Controller
{
    public function __construct(private TokenService $tokenService) {}

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'type' => 'required|in:admin,user'
        ]);

        $table = $request->type === 'admin' ? 'admins' : 'account';

        $user = DB::table($table)
            ->where('username', $request->username)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $this->tokenService->generate($user->id, $request->type);

        return response()->json([
            'token' => $token,
            'user_id' => $user->id,
            'role' => $request->type
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->bearerToken();

        $this->tokenService->logout($token);

        return response()->json([
            'message' => 'Logged out'
        ]);
    }
}
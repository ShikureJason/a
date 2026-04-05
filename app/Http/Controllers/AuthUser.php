<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

class AuthUser extends Controller
{
    public function login(Request $request)
        {
            $type = $request->type; // admin | user
            $table = $type === 'admin' ? 'admins' : 'account';

            $user = DB::table($table)
                ->where('username', $request->username)
                ->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            $ttl = 7200;
            $now = now();

            DB::beginTransaction();

            try {
                // 🔥 ดึง token เก่า (active)
                $oldToken = DB::table('auth_tokens')
                    ->where('user_id', $user->id)
                    ->where('type', $type)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                // 🔥 revoke token เก่า
                if ($oldToken) {
                    DB::table('auth_tokens')
                        ->where('id', $oldToken->id)
                        ->update([
                            'is_active' => false,
                            'revoked_at' => $now,
                        ]);
                }

                // 🔐 สร้าง token ใหม่
                $plainToken = Str::random(60);
                $hashed = hash('sha256', $plainToken);

                DB::table('auth_tokens')->insert([
                    'user_id' => $user->id,
                    'type' => $type,
                    'token' => $hashed,
                    'is_active' => true,
                    'expired_at' => $now->copy()->addSeconds($ttl),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::commit();

            } catch (\Throwable $e) {
                DB::rollBack();
                return response()->json(['message' => 'Login failed'], 500);
            }

            // 🚀 Redis sync
            $userKey = "user_token:$type:$user->id";

            // ลบ token เก่าใน Redis
            if (!empty($oldToken)) {
                Redis::del("auth:{$oldToken->token}");
            }

            // set token ใหม่
            Redis::setex("auth:$hashed", $ttl, json_encode([
                'user_id' => $user->id,
                'type' => $type,
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]));

            Redis::setex($userKey, $ttl, $hashed);

            return response()->json([
                'token' => $plainToken
            ]);
        }

    public function logout(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No token'], 400);
        }

        $hashed = hash('sha256', $token);

        DB::table('auth_tokens')
            ->where('token', $hashed)
            ->update([
                'is_active' => false,
                'revoked_at' => now()
            ]);

        // ลบ Redis
        $data = Redis::get("auth:$hashed");

        if ($data) {
            $data = json_decode($data, true);
            Redis::del("auth:$hashed");
            Redis::del("user_token:{$data['type']}:{$data['user_id']}");
        }

        return response()->json(['message' => 'Logged out']);
    }

}

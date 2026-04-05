<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TokenService
{
    private $ttl = 60 * 60 * 24 * 7; // 7 วัน

    public function generate($userId, $role)
    {
        $token = Str::random(60);

        Redis::hmset("token:$token", [
            'user_id' => $userId,
            'role' => $role
        ]);

        Redis::expire("token:$token", $this->ttl);

        return $token;
    }

    public function validate($token)
    {
        $data = Redis::hgetall("token:$token");

        if (!$data) {
            return null;
        }

        // 🔥 sliding session
        Redis::expire("token:$token", $this->ttl);

        return $data; // ['user_id' => ..., 'role' => ...]
    }

    public function logout($token)
    {
        Redis::del("token:$token");
    }
}
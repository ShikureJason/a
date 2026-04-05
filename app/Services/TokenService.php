<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TokenService
{
    private $ttl = 3600; // 1 ชั่วโมง

    public function generate($userId)
    {
        $token = Str::random(60);
        $expireAt = Carbon::now()->addSeconds($this->ttl);

        DB::table('user_tokens')->updateOrInsert(
            ['user_id' => $userId],
            [
                'token' => $token,
                'expired_at' => $expireAt,
                'updated_at' => now(),
                'created_at' => now()
            ]
        );

        // ✅ cache ลง Redis
        Redis::setex("token:$token", $this->ttl, $userId);
        Redis::setex("user:$userId:token", $this->ttl, $token);

        return $token;
    }

    public function validate($token)
    {
        // 🔥 1. เช็ค Redis ก่อน
        $userId = Redis::get("token:$token");

        if ($userId) {
            return $userId;
        }

        // 🔥 2. fallback DB
        $record = DB::table('user_tokens')
            ->where('token', $token)
            ->first();

        if (!$record) return null;

        // 🔥 3. check expire
        if ($record->expired_at && now()->gt($record->expired_at)) {
            return null;
        }

        // 🔥 4. cache กลับ Redis
        $ttl = now()->diffInSeconds($record->expired_at, false);

        if ($ttl > 0) {
            Redis::setex("token:$token", $ttl, $record->user_id);
            Redis::setex("user:{$record->user_id}:token", $ttl, $token);
        }

        return $record->user_id;
    }

    public function revoke($userId)
    {
        $token = Redis::get("user:$userId:token");

        if ($token) {
            Redis::del("token:$token");
        }

        Redis::del("user:$userId:token");

        DB::table('user_tokens')->where('user_id', $userId)->delete();
    }
}
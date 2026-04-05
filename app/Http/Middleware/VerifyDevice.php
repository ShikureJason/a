<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class VerifyDevice
{
    public function handle($request, Closure $next)
    {
        $deviceId  = $request->device_id;
        $timestamp = $request->timestamp;
        $nonce     = $request->nonce;
        $signature = $request->signature;
        $data      = $request->data;
        $ip        = $request->ip();

        // -----------------------------
        // 0. basic check
        // -----------------------------
        if (!$deviceId || !$nonce || !$signature || !$data) {
            return response()->json(['error' => 'Bad request'], 400);
        }

        // -----------------------------
        // 1. format check
        // -----------------------------
        if (!preg_match('/^esp32_[0-9]{3}$/', $deviceId)) {
            return response()->json(['error' => 'Invalid format'], 400);
        }

        // -----------------------------
        // 2. global rate limit (IP)
        // -----------------------------
        $ipKey = "ip_rate:$ip";

        $ipCount = Redis::incr($ipKey);
        if ($ipCount == 1) {
            Redis::expire($ipKey, 1);
        }

        if ($ipCount > 200) { // ปรับสูงหน่อย (รองรับหลาย device)
            return response()->json(['error' => 'Too many requests (ip)'], 429);
        }

        // -----------------------------
        // 3. check valid device (Redis set)
        // -----------------------------
        if (!Redis::sismember('valid_devices', $deviceId)) {

            $invalidKey = "invalid:$deviceId";

            $invalidCount = Redis::incr($invalidKey);
            if ($invalidCount == 1) {
                Redis::expire($invalidKey, 60);
            }

            if ($invalidCount > 5) {
                return response()->json(['error' => 'Blocked invalid device'], 429);
            }

            return response()->json(['error' => 'Invalid device'], 403);
        }

        // -----------------------------
        // 4. rate limit (per device)
        // -----------------------------
        $rateKey = "rate:$deviceId";

        $count = Redis::incr($rateKey);
        if ($count == 1) {
            Redis::expire($rateKey, 1);
        }

        if ($count > 10) {
            return response()->json(['error' => 'Too many requests'], 429);
        }

        // -----------------------------
        // 5. load device (cache + DB)
        // -----------------------------
        $deviceKey = "device:$deviceId";

        $device = Redis::get($deviceKey);

        if ($device === 'null') {
            return response()->json(['error' => 'Invalid device'], 403);
        }

        if (!$device) {
            $device = DB::table('devices')
                ->where('device_id', $deviceId)
                ->first();

            if (!$device || $device->revoked) {
                Redis::setex($deviceKey, 60, 'null');
                return response()->json(['error' => 'Invalid device'], 403);
            }

            Redis::setex($deviceKey, 300, json_encode($device));
        } else {
            $device = json_decode($device);
        }

        // -----------------------------
        // 6. timestamp check
        // -----------------------------
        if ($timestamp && abs(time() - $timestamp) > 120) {
            return response()->json(['error' => 'Expired'], 403);
        }

        // -----------------------------
        // 7. nonce check (replay)
        // -----------------------------
        $nonceKey = "nonce:$deviceId:$nonce";

        if (Redis::exists($nonceKey)) {
            return response()->json(['error' => 'Replay attack'], 403);
        }

        // -----------------------------
        // 8. validate data
        // -----------------------------
        if (!is_array($data)) {
            return response()->json(['error' => 'Invalid data format'], 422);
        }

        if (isset($data['temp'])) {
            if (!is_numeric($data['temp']) || $data['temp'] < -50 || $data['temp'] > 100) {
                return response()->json(['error' => 'Invalid temp'], 422);
            }
        }

        // -----------------------------
        // 9. verify HMAC (สำคัญ!)
        // -----------------------------
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $payload = $deviceId . $timestamp . $nonce . $dataJson;

        $expected = hash_hmac('sha256', $payload, $device->secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // -----------------------------
        // 10. deduplicate (หลัง HMAC เท่านั้น)
        // -----------------------------
        $dataHash = md5($dataJson);
        $dupKey = "dup:$deviceId:$dataHash";

        if (Redis::exists($dupKey)) {
            return response()->json(['error' => 'Duplicate data'], 409);
        }

        // -----------------------------
        // 11. save nonce + dedup (pipeline)
        // -----------------------------
        Redis::pipeline(function ($pipe) use ($dupKey, $nonceKey) {
            $pipe->setex($dupKey, 10, 1);
            $pipe->setex($nonceKey, 60, 1);
        });

        return $next($request);
    }
}
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class GatewaySession extends Controller
{
    function createSession($locationId ,$duration = 3600)
    {
        $location = DB::table('beacon_location')->find($locationId);

        if (!$location) {
            return response()->json([
                'status' => 'error',
                'code'   => 'Location not found',
            ], 404);
        }

        if (!DB::table('beacon')->where('location_id', $locationId)->exists()) {
            return response()->json(['message' => 'No device in this location'], 404);
        }

        $sessionId = (string) Str::uuid();

        $metaKey = "gateway:$sessionId:meta";

        Redis::hset($metaKey, [
            'created_at' => now()->timestamp,
            'expire_at'  => now()->addSeconds($duration)->timestamp,
            'location'   => $locationId,
        ]);

        Redis::expire($metaKey, $duration);

        return response()->json([
            'session_id' => $sessionId
        ], 200);
    }

    function closeSession($sessionId)
    {
        $key = "gateway:$sessionId";

        $data = Redis::lrange($key, 0, -1);

        if (empty($data)) {
            return response()->json(['status' => 'empty']);
        }

        $insertData = [];

        foreach ($data as $item) {
            $row = json_decode($item, true);

            if (!$row || !isset($row['device_id'])) {
                continue;
            }

            $insertData[] = [
                'session_id' => $sessionId,
                'device_id'  => $row['device_id'],
                'data'       => json_encode($row['data'] ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::transaction(function () use ($insertData, $sessionId, $key) {

            if (!empty($insertData)) {
                DB::table('device_logs')->insert($insertData);
            }

            Redis::del($key);
            Redis::del("gateway:$sessionId:meta");
            Redis::del("session_data:$sessionId");
            Redis::del("session_devices:$sessionId");
        });

        return response()->json([
            'status' => 'closed',
            'count'  => count($insertData)
        ]);
    }
}


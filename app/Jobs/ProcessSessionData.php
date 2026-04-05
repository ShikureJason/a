<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class ProcessSessionData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $sessionId;
    protected $closeSession;
    /**
     * Create a new job instance.
     */
    public function __construct($sessionId, $closeSession)
    {
        $this->sessionId = $sessionId;
        $this->closeSession = $closeSession;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $sessionId = $this->sessionId;

        // 🔹 ดึง user ทั้งหมด
        $users = Redis::smembers("session:$sessionId:users");
        $meta = Redis::hgetall("session:$sessionId:meta");
        $groupId = $meta['location'] ?? null;
        $startSession = $meta['created_at'] ?? null;

        $rows = [];

        foreach ($users as $userId) {

            $key = "session:$sessionId:users:$userId:live";
            $data = Redis::hgetall($key);

            if (empty($data)) continue;

            $count    = (int)($data['count'] ?? 0);
            $timeSum = (int)($data['time_sum'] ?? 0);
            $rssiSum  = (float)($data['rssi_sum'] ?? 0);

            if ($count <= 0) continue;

            $rssiAvg = $rssiSum / $count;

            $rows[] = [
                'location_id' => $groupId,
                'user_id'    => $userId,
                'rssi_avg'   => $rssiAvg,
                'time'      => $timeSum,
                'start_session' => $startSession,
                'close_session' => $this->closeSession,
                'created_at' => now(),
            ];
        }

        // 🔹 insert batch
        if (!empty($rows)) {
            DB::table('history')->insert($rows);
        }

        // 🔹 ลบ Redis keys ของ session
        $this->cleanupRedis($sessionId);
    }

    /**
     * ลบ Redis ทั้ง session
     */
    protected function cleanupRedis($sessionId)
    {
        $cursor = 0;

        do {
            [$cursor, $keys] = Redis::scan($cursor, [
                'match' => "session:$sessionId:*",
                'count' => 200
            ]);

            if (!empty($keys)) {
                Redis::pipeline(function ($pipe) use ($keys) {
                    foreach ($keys as $key) {
                        $pipe->del($key);
                    }
                });
            }

        } while ($cursor != 0);
    }
}
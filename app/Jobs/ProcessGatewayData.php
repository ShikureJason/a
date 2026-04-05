<?php
namespace App\Jobs;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessGatewayData implements ShouldQueue
{
    protected $sessionId;
    protected $gatewayId;
    protected $devices;
    protected $time;

    public function __construct($sessionId, $gatewayId, $devices, $time)
    {
        $this->sessionId = $sessionId;
        $this->gatewayId = $gatewayId;
        $this->devices = $devices;
        $this->time = $time;
    }

    public function handle()
    {
        $now = time();

        $bufferKey = "buffer:$this->sessionId:data";
        $startKey  = "buffer:$this->sessionId:start";

        // set start time
        if (!Redis::exists($startKey)) {
            Redis::set($startKey, $now);
            Redis::expire($startKey, 10);
        }

        // เก็บข้อมูล device
        Redis::hset($bufferKey, $this->gatewayId, json_encode($this->devices));
        Redis::expire($bufferKey, 10);

        $count = Redis::hlen($bufferKey);

        //เช็ค device online 
        $activeDevices = Redis::scard("gateway:$this->sessionId:devices");

        $timeout = 5;

        $startTime = Redis::get($startKey);

        if ($count >= $activeDevices || ($now - $startTime) >= $timeout) {

            $this->aggregate($bufferKey);

            Redis::del($bufferKey);
            Redis::del($startKey);
        }
    }

    private function aggregate($bufferKey)
    {
        $all = Redis::hgetall($bufferKey);

        $bestUsers = [];
        // 🔥 batch DB

        foreach ($all as $deviceId => $json) {

            $deviceData = json_decode($json, true);

            foreach ($deviceData as $d) {

                $userSession = $d['user_session'] ?? null;
                $rssi = $d['rssi'] ?? null;
                $time = $data['time'] ?? $this->time;

                if (!$userSession || !$rssi) continue;

                $user = $users[$userSession] ?? null;
                if (!$user) continue;

                $userId = Redis::get("gateway:$userSession:user");;

                // 🔥 เลือก RSSI ดีสุด
                if (!isset($bestUsers[$userId]) ||
                    $rssi > $bestUsers[$userId]['rssi']) {

                    $bestUsers[$userId] = [
                        'rssi' => $rssi,
                        'device' => $deviceId,
                        'time' => $time,
                        'count' => 0
                    ];
                }
            }
        }
        
        $sessionId = $this->sessionId;
        // 🔥 save (pipeline)
        $now = time();

        Redis::pipeline(function ($pipe) use ($bestUsers, $sessionId, $now) {

            foreach ($bestUsers as $userId => $data) {

                $key = "gateway:$sessionId:user:$userId:live";

                // =========================
                // ของเดิม
                // =========================
                $pipe->hincrbyfloat($key, 'rssi_sum', $data['rssi']);
                $pipe->hincrby($key, 'time_sum', $data['time']);
                $pipe->hincrby($key, 'count', 1);

                $pipe->hset($key, 'last_device', $data['device']);
                $pipe->hset($key, 'last_seen', $this->time);
                $pipe->hsetnx($key, 'first_active', $this->time);

                // =========================
                // 🔥 เพิ่มตรงนี้ (สำคัญ)
                // =========================

                // 🟢 global online user
                $pipe->zadd('online:all', $now, $userId);

                // 🟢 online ตาม session (ละเอียดขึ้น)
                $pipe->zadd("online:session:$sessionId", $now, $userId);

                // 🟢 optional: role (ถ้าคุณมี)
                $pipe->zadd("online:user", $now, $userId);

                // 🟢 last seen user
                $pipe->hset("user:$userId", 'last_seen', $now);
                $pipe->expire("user:$userId", 300);
            }
        });
    }
}
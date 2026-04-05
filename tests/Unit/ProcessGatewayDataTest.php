<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Redis;
use App\Jobs\ProcessGatewayData;

class ProcessGatewayDataTest extends TestCase
{
    public function test_handle_triggers_aggregate_when_condition_met()
    {
        $sessionId = 'session-1';
        $gatewayId = 'gw-1';
        $devices = [];
        $time = time();

        Redis::shouldReceive('exists')->andReturn(false);
        Redis::shouldReceive('set')->once();
        Redis::shouldReceive('expire')->andReturn(true);

        Redis::shouldReceive('hset')->once();
        Redis::shouldReceive('hlen')->andReturn(2);

        Redis::shouldReceive('scard')->andReturn(2);

        Redis::shouldReceive('get')->andReturn($time - 10);

        Redis::shouldReceive('hgetall')->andReturn([]);

        Redis::shouldReceive('del')->times(2);

        Redis::shouldReceive('pipeline')->once()->andReturnUsing(function ($callback) {
            $pipe = new class {
                public function hincrbyfloat() {}
                public function hincrby() {}
                public function hset() {}
                public function hsetnx() {}
            };
            $callback($pipe);
        });

        $job = new ProcessGatewayData($sessionId, $gatewayId, $devices, $time);
        $job->handle();

        $this->assertTrue(true); // ถ้าไม่ error = ผ่าน
    }
}
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use App\Jobs\ProcessGatewayData;

class GatewayControllerTest extends TestCase
{
    public function test_upload_dispatches_job_and_updates_redis()
    {
        Queue::fake();
        Redis::shouldReceive('zadd')->once();

        $payload = [
            'gateway_id' => 'gw-1',
            'session' => 'session-123',
            'devices' => [
                [
                    'user_session' => 'abc',
                    'rssi' => -40
                ]
            ]
        ];

        $response = $this->postJson('/api/esp/upload', $payload);

        $response->assertStatus(200)
                 ->assertJson(['status' => 'queued']);

        Queue::assertPushed(ProcessGatewayData::class, function ($job) use ($payload) {
            return $job->sessionId === $payload['session']
                && $job->gatewayId === $payload['gateway_id'];
        });
    }
}
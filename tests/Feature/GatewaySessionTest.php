<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class GatewaySessionTest extends TestCase
{
        use RefreshDatabase;
        use DatabaseTransactions;

    public function test_create_session_requires_auth()
    {
        
        $locationId = GatewaySessionTest::create_location_database_once();
        $response = $this->postJson(route('session.create', [
            'locationId' => $locationId,
        ]));

        $response->assertUnauthorized(); // 401
    }

     public function test_create_session_success()
    {
        Redis::flushdb();

        $locationId = GatewaySessionTest::create_location_database_once();

        $devices = [];

        for ($i = 1; $i <= 5; $i++) {
            $devices[] = [
                'device_id'  => Str::uuid(),
                'secret'     => Str::random(32),
                'revoked'    => false,
                'location_id'=> $locationId,
            ];
        }

        DB::table('beacon')->insert($devices);

        $response = $this->postJson(route('session.create', [
            'locationId' => $locationId
        ]));

        $response->assertOk()
                 ->assertJsonStructure(['session_id']);

        $sessionId = $response->json('session_id');

        $this->assertNotEmpty($sessionId);

        $meta = Redis::hgetall("gateway:$sessionId:meta");

        $this->assertNotEmpty($meta);
        $this->assertArrayHasKey('location', $meta);
        $this->assertEquals($locationId, $meta['location']);
    }

    public function test_create_session_fail_if_location_not_found()
    {
        $response = $this->postJson(route('session.create', [
            'locationId' => 9999
        ]));

        $response->assertStatus(404);
    }

    public function test_close_session_moves_data_to_db()
    {
        $sessionId = 'test-session';

        Redis::rpush("gateway:$sessionId", json_encode([
            'device_id' => 'dev01',
            'data' => ['rssi' => -50]
        ]));

        $response = $this->postJson("/api/session/close/$sessionId");

        $response->assertOk();

        $this->assertDatabaseHas('device_logs', [
            'session_id' => $sessionId,
            'device_id' => 'dev01'
        ]);
    }

    public function test_close_session_clears_redis()
    {
        $sessionId = 'test-session-2';

        Redis::rpush("gateway:$sessionId", json_encode([
            'device_id' => 'dev01',
            'data' => []
        ]));

        $this->postJson("/api/session/close/$sessionId");

        $this->assertEmpty(
            Redis::lrange("gateway:$sessionId", 0, -1)
        );
    }

    public function test_close_session_empty()
    {
        $sessionId = 'empty-session';

        $response = $this->postJson("/api/session/close/$sessionId");

        $response->assertOk()
                 ->assertJson(['status' => 'empty']);
    }

    private function create_location_database_once()
    {
        $facultyId = DB::table('faculty')->insertGetId([
            'name' => 'Engineering'
        ]);

        $locationId = DB::table('beacon_location')->insertGetId([
            'faculty_id' => $facultyId,
            'location'   => 'Building A'
        ]);
        return $locationId;
    }
}

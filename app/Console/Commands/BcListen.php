<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;

class BcListen extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bc:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // $mqtt = new MqttClient('mqtt', 1883, 'listener');
        // $mqtt->connect();

        // $mqtt->subscribe('device/+/response', function ($topic, $message) {

        //     preg_match('/device\/(.*?)\/response/', $topic, $matches);
        //     $deviceId = $matches[1];

        //     Redis::set("device:$deviceId:last_response", $message);
        //     Redis::set("device:$deviceId:last_seen", now()->timestamp);

        // }, 0);

        // $mqtt->loop(true);
        $mqtt = new MqttClient('mqtt', 1883, 'test-client');
        $mqtt->connect();

        echo "CONNECTED";
    }
}

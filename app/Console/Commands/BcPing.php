<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpMqtt\Client\MqttClient;

class BcPing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bc:ping';

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
        $mqtt = new MqttClient('mqtt', 1883, 'ping-loop');
        $mqtt->connect();

        while (true) {

            $devices = Redis::smembers('esp32:list');

            foreach ($devices as $deviceId) {
                $mqtt->publish("device/$deviceId/cmd", "status");
            }

            sleep(10);
        }
    }
}

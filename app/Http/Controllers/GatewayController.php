<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class GatewayController extends Controller
{

    public function upload(Request $req)
    {
        $gatewayId = $req->gateway_id;
        $sessionId = $req->session;
        $now = time();

        // ✅ mark device online (ZSET)
        //Redis::zadd("session:$sessionId:devices", $now, $gatewayId);

        // ✅ push เข้า queue
        dispatch(new \App\Jobs\ProcessGatewayData(
            $sessionId,
            $gatewayId,
            $req->devices,
            $now,
        ));

        return response()->json(['status' => 'queued'], 200);
    }

}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        try {
            DB::select('select 1');
            $checks['database'] = 'ok';
        } catch (\Throwable) {
            $checks['database'] = 'unavailable';
        }try {
            $probe = 'health/'.bin2hex(random_bytes(4));
            Storage::put($probe, 'ok');
            $checks['storage'] = Storage::exists($probe) ? 'ok' : 'unavailable';
            Storage::delete($probe);
        } catch (\Throwable) {
            $checks['storage'] = 'unavailable';
        }$checks['queue'] = config('queue.default');
        $checks['failed_jobs'] = DB::table('failed_jobs')->count();
        if (config('cache.default') === 'redis' || config('queue.default') === 'redis') {
            try {
                Redis::connection()->ping();
                $checks['redis'] = 'ok';
            } catch (\Throwable) {
                $checks['redis'] = 'unavailable';
            }
        }$ok = ! in_array('unavailable', $checks, true);

        return response()->json(['data' => $checks, 'meta' => [], 'error' => $ok ? null : ['code' => 'SYSTEM_DEGRADED', 'message' => 'One or more services are unavailable.']], $ok ? 200 : 503);
    }
}

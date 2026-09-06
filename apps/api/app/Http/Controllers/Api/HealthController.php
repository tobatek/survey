<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

// Liveness murni ("proses dapat menjawab") sudah disediakan Laravel bawaan di
// GET /up (didaftarkan lewat bootstrap/app.php health: '/up'). Controller ini
// menambahkan readiness: DB/Redis harus tersambung sebelum instance dianggap
// siap menerima traffic — FSD §23.2. Endpoint ini publik (dipakai load
// balancer/orchestrator awal), sehingga TIDAK boleh membocorkan detail
// koneksi (connection string, stack trace) pada respons.
class HealthController extends Controller
{
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $healthy = ! in_array(false, $checks, strict: true);

        return response()->json([
            'status' => $healthy ? 'ready' : 'not_ready',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

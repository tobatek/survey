<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

// Liveness murni ("proses dapat menjawab") sudah disediakan Laravel bawaan di
// GET /up (didaftarkan lewat bootstrap/app.php health: '/up'). Controller ini
// menambahkan readiness: DB/Redis harus tersambung sebelum instance dianggap
// siap menerima traffic — FSD §23.2. Endpoint ready() ini publik (dipakai load
// balancer/orchestrator awal), sehingga TIDAK boleh membocorkan detail
// koneksi (connection string, stack trace) pada respons. deep() (Fase 6
// paket E) sebaliknya BOLEH detail karena digerbang role technical_admin.
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

    // FSD §23.2 "deep dependency health hanya untuk admin/monitor, bukan
    // public" (Fase 6 paket E) -- gerbang role dicek di routes/api.php
    // (middleware `role:technical_admin`), bukan di sini, konsisten pola
    // otorisasi endpoint lain di project ini. Beda dari ready(): boleh
    // membocorkan detail (waktu ping, panjang antrean, dst) karena hanya
    // admin teknis yang bisa mengaksesnya.
    public function deep(): JsonResponse
    {
        return response()->json(['data' => [
            'database' => $this->databaseDetail(),
            'redis' => $this->redisDetail(),
            'queues' => $this->queueDepths(),
            'failed_jobs' => $this->failedJobsCount(),
            'disk_free_bytes' => disk_free_space(Storage::disk('local')->path('')),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseDetail(): array
    {
        $start = microtime(true);

        try {
            DB::connection()->getPdo();

            return ['connected' => true, 'ping_ms' => round((microtime(true) - $start) * 1000, 1)];
        } catch (\Throwable $e) {
            return ['connected' => false, 'error' => class_basename($e)];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function redisDetail(): array
    {
        $start = microtime(true);

        try {
            Redis::connection()->ping();
            $pingMs = round((microtime(true) - $start) * 1000, 1);
            $info = Redis::connection()->info('memory');
            // phpredis mengelompokkan hasil INFO per section ("Memory" =>
            // [...]) sementara predis mengembalikan flat -- dukung dua-duanya.
            $memory = $info['Memory'] ?? $info;

            return [
                'connected' => true,
                'ping_ms' => $pingMs,
                'used_memory_human' => $memory['used_memory_human'] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['connected' => false, 'error' => class_basename($e)];
        }
    }

    /**
     * @return array<string, int|null>
     */
    private function queueDepths(): array
    {
        $names = ['exports', 'notifications', 'default'];
        $depths = [];

        foreach ($names as $name) {
            try {
                $depths[$name] = Queue::connection('redis')->size($name);
            } catch (\Throwable) {
                $depths[$name] = null;
            }
        }

        return $depths;
    }

    private function failedJobsCount(): ?int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return null;
        }
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

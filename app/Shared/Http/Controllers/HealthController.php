<?php

namespace Shared\Http\Controllers;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __construct(private readonly Client $es) {}

    public function __invoke(): JsonResponse
    {
        $db = $this->checkDb();
        $es = $this->checkEs();
        $redis = $this->checkRedis();

        $allHealthy = $db && $es && $redis;

        return response()->json([
            'status' => $allHealthy ? 'ok' : 'degraded',
            'db' => $db,
            'es' => $es,
            'redis' => $redis,
        ], $allHealthy ? 200 : 503);
    }

    private function checkDb(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Health check: DB unavailable', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function checkEs(): bool
    {
        try {
            $response = $this->es->ping();

            return $response instanceof Elasticsearch && $response->asBool();
        } catch (\Throwable $e) {
            Log::warning('Health check: Elasticsearch unavailable', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Health check: Redis unavailable', ['error' => $e->getMessage()]);

            return false;
        }
    }
}

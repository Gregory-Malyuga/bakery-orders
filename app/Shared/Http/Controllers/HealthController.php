<?php

namespace Shared\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $db = $this->checkDb();

        return response()->json([
            'status' => $db ? 'ok' : 'degraded',
            'db' => $db,
        ], $db ? 200 : 503);
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
}

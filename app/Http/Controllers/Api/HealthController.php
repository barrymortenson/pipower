<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NutService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(NutService $nutService): JsonResponse
    {
        $health = $nutService->healthCheck();

        if (! $health['healthy']) {
            return response()->json([
                'status' => 'error',
                'message' => $health['error'],
            ], 503);
        }

        $status = $nutService->getStatus();

        if (! $status || $status['on_battery'] || $status['battery_low']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Power issue detected',
            ], 503);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'System healthy',
        ], 200);
    }
}

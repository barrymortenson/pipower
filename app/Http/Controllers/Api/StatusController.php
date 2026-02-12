<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\NutService;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __invoke(NutService $nutService): JsonResponse
    {
        $upsStatus = $nutService->getStatus();
        $health = $nutService->healthCheck();
        $lastEvent = Event::latest()->first();

        $onBattery = $upsStatus['on_battery'] ?? false;

        // Find when we went on battery
        $onBatterySince = null;
        if ($onBattery) {
            $lastPowerLost = Event::where('type', 'power_lost')->latest()->first();
            $onBatterySince = $lastPowerLost?->created_at?->toIso8601String();
        }

        return response()->json([
            'power' => [
                'status' => $onBattery ? 'on_battery' : 'on_mains',
                'on_battery_since' => $onBatterySince,
            ],
            'ups' => [
                'connected' => $health['healthy'] && ($upsStatus !== null),
                'model' => $upsStatus['model'] ?? null,
                'battery_level' => $upsStatus['battery_level'] ?? null,
                'battery_low' => $upsStatus['battery_low'] ?? false,
                'battery_runtime' => $upsStatus['battery_runtime'] ?? null,
                'charging' => $upsStatus['charging'] ?? false,
                'load_percent' => $upsStatus['load_percent'] ?? null,
                'on_battery' => $onBattery,
            ],
            'device' => [
                'hostname' => gethostname() ?: 'pipower',
                'ip' => $this->getLocalIp(),
                'uptime_seconds' => $this->getUptime(),
                'version' => config('pipower.version'),
            ],
            'last_event' => $lastEvent ? [
                'type' => $lastEvent->type,
                'timestamp' => $lastEvent->created_at->toIso8601String(),
            ] : null,
        ]);
    }

    private function getLocalIp(): ?string
    {
        $result = shell_exec("hostname -I 2>/dev/null | awk '{print $1}'");

        return $result ? trim($result) : request()->server('SERVER_ADDR');
    }

    private function getUptime(): int
    {
        $uptime = shell_exec("cat /proc/uptime 2>/dev/null | awk '{print $1}'");

        return $uptime ? (int) $uptime : 0;
    }
}

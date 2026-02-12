<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class PiPowerService
{
    public function __construct(
        private NutService $nutService,
        private NotificationService $notificationService
    ) {}

    public function checkAndNotify(): void
    {
        $health = $this->nutService->healthCheck();
        $status = $this->nutService->getStatus();

        $this->checkUpsConnection($health['healthy']);

        if (! $health['healthy'] || ! $status) {
            return;
        }

        $this->checkPowerState($status['on_battery'], $status['battery_level'], $status['battery_runtime'] ?? null);
        $this->checkBatteryLow($status['battery_low'], $status['battery_level']);
        $this->checkRuntimeCritical($status['on_battery'], $status['battery_runtime'] ?? null);
    }

    private function checkUpsConnection(bool $isConnected): void
    {
        $wasConnected = Setting::get('last_ups_connected', true);

        if ($wasConnected && ! $isConnected) {
            $this->createEventAndNotify('ups_disconnected', 'Lost communication with UPS');
        } elseif (! $wasConnected && $isConnected) {
            $this->createEventAndNotify('ups_reconnected', 'Communication with UPS restored');
        }

        Setting::set('last_ups_connected', $isConnected);
    }

    private function checkPowerState(bool $onBattery, int $batteryLevel, ?int $batteryRuntime): void
    {
        $lastState = Setting::get('last_power_state', 'on_mains');
        $currentState = $onBattery ? 'on_battery' : 'on_mains';

        if ($lastState === 'on_mains' && $currentState === 'on_battery') {
            // Store outage start time
            Setting::set('outage_started_at', now()->toIso8601String());
            $message = $this->buildPowerLostMessage($batteryRuntime);
            $this->createEventAndNotify('power_lost', $message);
        } elseif ($lastState === 'on_battery' && $currentState === 'on_mains') {
            $message = $this->buildPowerRestoredMessage($batteryLevel);
            $this->createEventAndNotify('power_restored', $message);
            // Clear outage tracking
            Setting::set('outage_started_at', null);
            Setting::set('runtime_critical_notified', false);
        }

        Setting::set('last_power_state', $currentState);
    }

    private function buildPowerRestoredMessage(int $batteryLevel): string
    {
        $outageStarted = Setting::get('outage_started_at');

        if (! $outageStarted) {
            return "Power has been restored (battery at {$batteryLevel}%)";
        }

        $duration = now()->diffForHumans(
            \Carbon\Carbon::parse($outageStarted),
            ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]
        );

        return "Power has been restored after {$duration} (battery at {$batteryLevel}%)";
    }

    private function buildPowerLostMessage(?int $batteryRuntime): string
    {
        $base = 'Power outage detected, running on battery';

        if ($batteryRuntime && $batteryRuntime > 0) {
            $runtime = $this->formatRuntime($batteryRuntime);

            return "{$base} (~{$runtime} remaining)";
        }

        return $base;
    }

    private function formatRuntime(int $seconds): string
    {
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }

        return "{$minutes} min";
    }

    private function checkBatteryLow(bool $batteryLow, int $batteryLevel): void
    {
        $wasBatteryLow = Setting::get('last_battery_low', false);

        if (! $wasBatteryLow && $batteryLow) {
            $this->createEventAndNotify('battery_low', "Battery level critical at {$batteryLevel}%");
        }

        Setting::set('last_battery_low', $batteryLow);
    }

    private function checkRuntimeCritical(bool $onBattery, ?int $batteryRuntime): void
    {
        // Only check when on battery and runtime is available
        if (! $onBattery || $batteryRuntime === null) {
            return;
        }

        $threshold = 600; // 10 minutes in seconds
        $alreadyNotified = Setting::get('runtime_critical_notified', false);

        if (! $alreadyNotified && $batteryRuntime < $threshold) {
            $runtime = $this->formatRuntime($batteryRuntime);
            $this->createEventAndNotify(
                'runtime_critical',
                "Only {$runtime} of battery remaining"
            );
            Setting::set('runtime_critical_notified', true);
        }
    }

    private function createEventAndNotify(string $type, string $message): void
    {
        Event::create([
            'type' => $type,
            'message' => $message,
            'data' => [
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

        Log::info("UPS: {$message}");

        $this->notificationService->notify($type, $message);
    }

    public function getCurrentStatus(): array
    {
        $health = $this->nutService->healthCheck();
        $status = $this->nutService->getStatus();

        return [
            'ups_connected' => $health['healthy'],
            'ups_error' => $health['error'],
            'power_status' => $status ? ($status['on_battery'] ? 'on_battery' : 'on_mains') : 'unknown',
            'battery_level' => $status['battery_level'] ?? null,
            'battery_low' => $status['battery_low'] ?? false,
            'battery_runtime' => $status['battery_runtime'] ?? null,
            'load_percent' => $status['load_percent'] ?? null,
            'model' => $status['model'] ?? null,
            'on_battery' => $status['on_battery'] ?? false,
        ];
    }
}

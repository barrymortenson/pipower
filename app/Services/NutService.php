<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class NutService
{
    private string $host;

    private int $port;

    private string $upsName;

    public function __construct()
    {
        $this->host = config('pipower.nut.host', 'localhost');
        $this->port = config('pipower.nut.port', 3493);
        $this->upsName = config('pipower.nut.ups_name', 'ups');
    }

    /**
     * Get current UPS status from NUT daemon
     */
    public function getStatus(): ?array
    {
        $result = Process::path('/tmp')->run("/usr/bin/upsc {$this->upsName}@{$this->host}:{$this->port} 2>/dev/null");

        if (! $result->successful()) {
            return null;
        }

        return $this->parseStatus($result->output());
    }

    /**
     * Parse raw upsc output into structured array
     */
    public function parseStatus(string $rawOutput): array
    {
        $lines = explode("\n", trim($rawOutput));
        $data = [];

        foreach ($lines as $line) {
            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $data[trim($key)] = trim($value);
            }
        }

        $upsStatus = $data['ups.status'] ?? '';
        $batteryLevel = (int) ($data['battery.charge'] ?? 0);
        $batteryLowThreshold = (int) ($data['battery.charge.low'] ?? config('pipower.notifications.battery_low_threshold', 20));

        // battery.runtime is in seconds, null if not available
        $runtimeSeconds = isset($data['battery.runtime']) ? (int) $data['battery.runtime'] : null;

        return [
            'connected' => true,
            'model' => $data['device.model'] ?? $data['ups.model'] ?? 'Unknown',
            'battery_level' => $batteryLevel,
            'battery_low' => str_contains($upsStatus, 'LB') || $batteryLevel <= $batteryLowThreshold,
            'battery_runtime' => $runtimeSeconds,
            'charging' => str_contains($upsStatus, 'CHRG'),
            'load_percent' => (int) ($data['ups.load'] ?? 0),
            'on_battery' => str_contains($upsStatus, 'OB'),
            'raw_status' => $upsStatus,
        ];
    }

    /**
     * Check if NUT daemon is reachable and UPS is detected
     */
    public function healthCheck(): array
    {
        $result = Process::path('/tmp')->run("/usr/bin/upsc {$this->upsName}@{$this->host}:{$this->port} 2>&1");

        if (! $result->successful()) {
            $output = $result->output();

            if (str_contains($output, 'Connection refused')) {
                return [
                    'healthy' => false,
                    'error' => 'NUT daemon not running',
                ];
            }

            if (str_contains($output, 'Unknown UPS')) {
                return [
                    'healthy' => false,
                    'error' => 'UPS not found - check USB connection or driver compatibility',
                ];
            }

            return [
                'healthy' => false,
                'error' => 'Unknown error: '.trim($output),
            ];
        }

        return [
            'healthy' => true,
            'error' => null,
        ];
    }
}

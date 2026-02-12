<?php

namespace App\Console\Commands;

use App\Services\PiPowerService;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class StatusCommand extends Command
{
    protected $signature = 'power:status';

    protected $description = 'Display current power and UPS status';

    public function handle(PiPowerService $service): int
    {
        $status = $service->getCurrentStatus();

        if (! $status['ups_connected']) {
            error("UPS Not Connected: {$status['ups_error']}");

            return Command::FAILURE;
        }

        $powerIcon = $status['power_status'] === 'on_mains' ? '⚡' : '🔋';
        $powerLabel = $status['power_status'] === 'on_mains' ? 'On AC Power' : 'On Battery';

        info("{$powerIcon} Power Status: {$powerLabel}");

        table(
            ['Property', 'Value'],
            [
                ['UPS Model', $status['model'] ?? 'Unknown'],
                ['Battery Level', ($status['battery_level'] ?? 'N/A').'%'],
                ['Battery Low', $status['battery_low'] ? 'Yes' : 'No'],
                ['Load', ($status['load_percent'] ?? 'N/A').'%'],
            ]
        );

        return Command::SUCCESS;
    }
}

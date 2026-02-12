<?php

namespace App\Console\Commands;

use App\Services\PiPowerService;
use Illuminate\Console\Command;

class MonitorPowerCommand extends Command
{
    protected $signature = 'power:check';

    protected $description = 'Check UPS power status and send notifications if state changed';

    public function handle(PiPowerService $service): int
    {
        $service->checkAndNotify();

        $status = $service->getCurrentStatus();

        if (! $status['ups_connected']) {
            $this->error('UPS: Not connected');

            return Command::SUCCESS;
        }

        $powerStatus = $status['power_status'] === 'on_battery' ? 'On Battery' : 'On Mains';
        $battery = $status['battery_level'] ?? '?';

        $this->info("UPS: Connected | Power: {$powerStatus} | Battery: {$battery}%");

        return Command::SUCCESS;
    }
}

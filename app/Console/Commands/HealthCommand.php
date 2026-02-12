<?php

namespace App\Console\Commands;

use App\Services\NutService;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class HealthCommand extends Command
{
    protected $signature = 'power:health';

    protected $description = 'Check NUT daemon connection and UPS compatibility';

    public function handle(NutService $nutService): int
    {
        info('Checking NUT daemon connection...');

        $health = $nutService->healthCheck();

        if (! $health['healthy']) {
            error("Health Check Failed: {$health['error']}");

            if (str_contains($health['error'], 'not running')) {
                warning('Try: sudo systemctl start nut-server');
            } elseif (str_contains($health['error'], 'not found')) {
                warning('Check USB cable connection and UPS compatibility');
                warning('Run: lsusb | grep -i ups');
            }

            return Command::FAILURE;
        }

        info('✓ NUT daemon is running');
        info('✓ UPS is detected and communicating');

        $status = $nutService->getStatus();
        if ($status) {
            info("✓ UPS Model: {$status['model']}");
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;

class NotifyTestCommand extends Command
{
    protected $signature = 'power:notify-test';

    protected $description = 'Send test notification to all enabled recipients';

    public function handle(NotificationService $service): int
    {
        info('Sending test notifications...');

        $results = $service->sendTestNotification();

        $rows = [];
        foreach ($results['email'] as $result) {
            $rows[] = [
                'Email',
                $result['recipient'],
                $result['success'] ? '✓' : '✗ '.($result['error'] ?? ''),
            ];
        }
        foreach ($results['sms'] as $result) {
            $rows[] = [
                'SMS',
                $result['recipient'],
                $result['success'] ? '✓' : '✗ '.($result['error'] ?? ''),
            ];
        }

        if (empty($rows)) {
            error('No recipients configured');

            return Command::FAILURE;
        }

        table(['Type', 'Recipient', 'Status'], $rows);

        return Command::SUCCESS;
    }
}

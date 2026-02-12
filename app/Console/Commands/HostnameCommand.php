<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\SystemConfigService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

class HostnameCommand extends Command
{
    protected $signature = 'power:hostname';

    protected $description = 'Set the system hostname';

    public function handle(SystemConfigService $systemConfig): int
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            error('This command must be run on the Raspberry Pi.');

            return Command::FAILURE;
        }

        $currentHostname = $systemConfig->getCurrentHostname();

        info('');
        info('Current hostname: '.$currentHostname);
        info('');

        $hostname = text(
            label: 'New hostname:',
            placeholder: 'Oxnard-Power',
            default: $currentHostname,
            hint: 'Recommended format: Location-Power',
            required: true,
            validate: function (string $value) {
                if (! preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i', $value)) {
                    return 'Hostname must contain only letters, numbers, and hyphens, and cannot start or end with a hyphen.';
                }
                if (strlen($value) > 63) {
                    return 'Hostname must be 63 characters or less.';
                }

                return null;
            }
        );

        if ($hostname === $currentHostname) {
            info('Hostname unchanged.');

            return Command::SUCCESS;
        }

        info('');
        info('Applying hostname...');

        if (! $systemConfig->applyHostname($hostname)) {
            error('Failed to apply hostname.');

            return Command::FAILURE;
        }

        // Save to database for web UI consistency
        Setting::set('hostname', $hostname);

        info("Hostname set to: {$hostname}");
        info('');

        if (confirm('Reboot now to apply changes?', default: true)) {
            info('Rebooting...');
            $systemConfig->reboot();
        } else {
            info('Remember to reboot for changes to take full effect.');
        }

        return Command::SUCCESS;
    }
}

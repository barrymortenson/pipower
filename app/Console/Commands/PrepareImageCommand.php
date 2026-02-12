<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;

class PrepareImageCommand extends Command
{
    protected $signature = 'power-setup:prepare-image';

    protected $description = 'Clean up system packages before creating distributable image';

    public function handle(): int
    {
        // Check if running as root
        if (posix_geteuid() !== 0) {
            $this->error('This command must be run as root. Use: sudo php artisan power-setup:prepare-image');

            return 1;
        }

        info('Preparing system for image creation...');

        note('Removing unused packages...');
        Process::run('apt-get autoremove -y');

        note('Cleaning package cache...');
        Process::run('apt-get autoclean');

        $this->newLine();
        info('System cleaned. Ready for image creation.');
        note('Shut down with: sudo shutdown -h now');

        return 0;
    }
}

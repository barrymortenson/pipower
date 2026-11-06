<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class UpdateCommand extends Command
{
    protected $signature = 'power:update';

    protected $description = 'Check for and apply PiPower updates';

    public function handle(): int
    {
        info('');
        info('PiPower Update');
        info('==============');
        info('');

        // Check if running on Linux
        if (PHP_OS_FAMILY !== 'Linux') {
            error('This command must be run on the Raspberry Pi.');

            return Command::FAILURE;
        }

        // Check current version
        $currentVersion = $this->getCurrentVersion();
        info("Current version: {$currentVersion}");

        // Check for updates
        info('Checking for updates...');
        $updateInfo = $this->checkForUpdates();

        if (! $updateInfo['available']) {
            info('');
            info('✓ Already running the latest version.');

            return Command::SUCCESS;
        }

        info('');
        info("Update available: {$updateInfo['commits']} commit(s) behind");
        info('');

        if (! confirm('Apply update now?', default: true)) {
            warning('Update cancelled.');

            return Command::SUCCESS;
        }

        // Run update
        info('');
        info('Applying update...');
        info('');

        $result = Process::path('/tmp')
            ->timeout(600)
            ->tty()
            ->run('sudo '.escapeshellarg($this->repoDir().'/update.sh'));

        info('');

        if ($result->successful()) {
            $newVersion = $this->getCurrentVersion();
            info("✓ Update complete ({$newVersion})");
            info('');
            info('Note: Refresh your browser to see the updated web interface.');
        } else {
            error('Update failed. Check the output above for errors.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function getCurrentVersion(): string
    {
        $result = Process::path($this->repoDir())
            ->run('sudo -u pi git describe --tags --always 2>/dev/null || sudo -u pi git rev-parse --short HEAD');

        return trim($result->output()) ?: 'unknown';
    }

    private function checkForUpdates(): array
    {
        // Fetch latest from remote
        Process::path($this->repoDir())->run('sudo -u pi git fetch origin 2>/dev/null');

        // Check commits behind
        $result = Process::path($this->repoDir())
            ->run('sudo -u pi git rev-list HEAD..origin/main --count 2>/dev/null');

        $commitsBehind = (int) trim($result->output());

        return [
            'available' => $commitsBehind > 0,
            'commits' => $commitsBehind,
        ];
    }

    private function repoDir(): string
    {
        return config('pipower.repo_dir');
    }
}

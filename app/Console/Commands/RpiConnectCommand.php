<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class RpiConnectCommand extends Command
{
    protected $signature = 'power:rpi-connect';

    protected $description = 'Set up Raspberry Pi Connect for remote access';

    /**
     * Environment variables required for systemd user services when running via sudo.
     * XDG_RUNTIME_DIR: Points to user's runtime directory
     * DBUS_SESSION_BUS_ADDRESS: Required for D-Bus communication with user's systemd instance
     */
    private const USER_ENV = 'XDG_RUNTIME_DIR=/run/user/1000 DBUS_SESSION_BUS_ADDRESS=unix:path=/run/user/1000/bus';

    public function handle(): int
    {
        info('');
        info('Raspberry Pi Connect Setup');
        info('==========================');
        info('');

        // Check if running on Linux
        if (PHP_OS_FAMILY !== 'Linux') {
            error('This command must be run on the Raspberry Pi.');

            return Command::FAILURE;
        }

        // Check if rpi-connect is installed
        $checkResult = Process::path('/tmp')->run('which rpi-connect');
        if (! $checkResult->successful()) {
            info('Installing rpi-connect-lite...');
            $installResult = Process::path('/tmp')
                ->timeout(120)
                ->run('sudo apt-get update && sudo apt-get install -y rpi-connect-lite');

            if (! $installResult->successful()) {
                error('Failed to install rpi-connect-lite.');
                warning($installResult->errorOutput());

                return Command::FAILURE;
            }
            info('✓ rpi-connect-lite installed');
        } else {
            info('✓ rpi-connect already installed');
        }

        // Enable user lingering so Connect runs without active login session
        Process::path('/tmp')->run('sudo /usr/bin/loginctl enable-linger pi');
        info('✓ User lingering enabled');

        // Enable the service (must be done as pi user with proper session environment)
        $env = self::USER_ENV;
        Process::path('/tmp')->run("sudo -u pi {$env} rpi-connect on");
        info('✓ Raspberry Pi Connect service enabled');

        // Wait for service to start (up to 15 seconds)
        $ready = false;
        for ($i = 0; $i < 15; $i++) {
            sleep(1);
            $statusResult = Process::path('/tmp')->run("sudo -u pi {$env} rpi-connect status 2>&1");
            $output = $statusResult->output().$statusResult->errorOutput();

            // Service is ready if output doesn't contain "not running"
            if (! str_contains(strtolower($output), 'not running')) {
                $ready = true;
                break;
            }
        }

        if (! $ready) {
            error('Raspberry Pi Connect service did not start.');
            warning('Try running manually as pi user: rpi-connect on && rpi-connect signin');

            return Command::FAILURE;
        }

        info('✓ Raspberry Pi Connect service running');

        // Check current status
        $statusResult = Process::path('/tmp')->run("sudo -u pi {$env} rpi-connect status 2>&1");
        $output = $statusResult->output();

        if (str_contains($output, 'Signed in: yes') || str_contains($output, 'Signed in:yes')) {
            info('');
            info('✓ Already signed in to Raspberry Pi Connect');
            info('');
            info($output);

            return Command::SUCCESS;
        }

        // Prompt for auth key
        info('');
        info('To link this device, create an auth key at:');
        info('https://connect.raspberrypi.com → Settings → Auth keys → New');
        info('');

        $authKey = text(
            label: 'Auth key:',
            placeholder: 'rpoak_...',
            hint: 'Paste the auth key from connect.raspberrypi.com (or leave empty to skip)',
        );

        if (empty($authKey)) {
            warning('Skipped. You can set it up later by running: rpi-connect signin');

            return Command::SUCCESS;
        }

        // Validate auth key format
        if (! preg_match('/^rpoak_[a-zA-Z0-9]+$/', $authKey)) {
            error('Invalid auth key format. Expected: rpoak_...');

            return Command::FAILURE;
        }

        // Sign in with auth key (must run as pi user with session environment)
        $signinResult = Process::path('/tmp')
            ->timeout(30)
            ->run("sudo -u pi {$env} rpi-connect signin --auth-key={$authKey}");

        if ($signinResult->successful()) {
            info('');
            info('✓ Raspberry Pi Connect linked successfully!');
            info('');
            info('You can now access this device at: https://connect.raspberrypi.com');

            return Command::SUCCESS;
        }

        error('');
        error('Raspberry Pi Connect sign-in failed.');
        warning($signinResult->errorOutput());
        warning('You can try again later by running: rpi-connect signin');

        return Command::FAILURE;
    }
}

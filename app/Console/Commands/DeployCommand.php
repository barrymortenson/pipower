<?php

namespace App\Console\Commands;

use App\Services\SystemConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class DeployCommand extends Command
{
    protected $signature = 'power:deploy';

    protected $description = 'Configure hostname, network settings, and update to latest version';

    private const CMDLINE_PATH = '/boot/firmware/cmdline.txt';

    private const UPDATE_SCRIPT = '/home/pi/pipower/update.sh';

    public function handle(SystemConfigService $systemConfig): int
    {
        info('');
        info('PiPower Deployment');
        info('==================');
        info('');

        // Check if running on Linux
        if (PHP_OS_FAMILY !== 'Linux') {
            error('This command must be run on the Raspberry Pi.');

            return Command::FAILURE;
        }

        // Check if running as www-data (via pa alias) or root
        $currentUser = posix_getpwuid(posix_geteuid())['name'] ?? 'unknown';
        if ($currentUser !== 'www-data' && $currentUser !== 'root') {
            warning("Running as '{$currentUser}'. For best results, use: pa power:deploy");
        }

        // Parse network configuration from cmdline.txt or current system
        $networkConfig = $this->detectNetworkConfig();

        // Prompt for hostname
        $hostname = text(
            label: 'Hostname:',
            placeholder: 'Oxnard-Power',
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

        // Show detected network config and ask if user wants to change it
        info('');
        info("Detected IP: {$networkConfig['ip']} (Gateway: {$networkConfig['gateway']})");

        if (confirm('Change network settings?', default: true)) {
            $networkConfig['ip'] = text(
                label: 'Static IP:',
                default: $networkConfig['ip'],
                required: true,
                validate: fn ($v) => filter_var($v, FILTER_VALIDATE_IP) ? null : 'Enter a valid IP address'
            );

            $networkConfig['gateway'] = text(
                label: 'Gateway:',
                default: $networkConfig['gateway'],
                required: true,
                validate: fn ($v) => filter_var($v, FILTER_VALIDATE_IP) ? null : 'Enter a valid IP address'
            );

            $networkConfig['netmask'] = text(
                label: 'Netmask:',
                default: $networkConfig['netmask'],
                required: true,
                validate: fn ($v) => filter_var($v, FILTER_VALIDATE_IP) ? null : 'Enter a valid netmask'
            );
        }

        // Show configuration summary
        info('');
        info('Configuration Summary');
        info('---------------------');
        info("Hostname:   {$hostname}");
        info("Static IP:  {$networkConfig['ip']}");
        info("Gateway:    {$networkConfig['gateway']}");
        info("Netmask:    {$networkConfig['netmask']}");
        info("DNS:        {$networkConfig['dns']}");
        info('');

        // Apply NetworkManager configuration
        if ($systemConfig->configureNetwork($networkConfig['ip'], $networkConfig['gateway'], $networkConfig['netmask'], $networkConfig['dns'])) {
            info('✓ NetworkManager configured');
        } else {
            warning('⚠ Failed to configure NetworkManager');
        }

        // Set hostname
        if ($systemConfig->applyHostname($hostname)) {
            info('✓ Hostname set');
        } else {
            warning('⚠ Failed to set hostname');
        }

        // Remove IP override from cmdline.txt
        if ($networkConfig['from_cmdline']) {
            $this->removeCmdlineIpOverride();
            info('✓ cmdline.txt IP override removed');
        } else {
            info('✓ No cmdline.txt IP override to remove');
        }

        // Run update
        $version = $this->runUpdate();
        if ($version) {
            info("✓ Update complete ({$version})");
        } else {
            warning('⚠ Update skipped or failed (check network connectivity)');
        }

        info('');

        // Auto-reboot with countdown
        $this->rebootWithCountdown($systemConfig, 10);

        return Command::SUCCESS;
    }

    private function detectNetworkConfig(): array
    {
        $fromCmdline = false;
        $ip = null;
        $gateway = null;
        $netmask = null;

        // Try to parse from cmdline.txt first
        if (file_exists(self::CMDLINE_PATH)) {
            $cmdline = file_get_contents(self::CMDLINE_PATH);
            if (preg_match('/ip=([^:\s]+)::([^:\s]*):([^:\s]+):/', $cmdline, $matches)) {
                $ip = $matches[1];
                $gateway = $matches[2] ?: null;
                $netmask = $matches[3];
                $fromCmdline = true;
            }
        }

        // Fall back to current system configuration
        if (! $ip) {
            $ipResult = Process::path('/tmp')->run("ip -4 addr show eth0 | grep -oP '(?<=inet\\s)\\d+(\\.\\d+){3}'");
            $ip = trim($ipResult->output()) ?: '192.168.100.40';

            $gwResult = Process::path('/tmp')->run("ip route | grep default | grep -oP '(?<=via\\s)\\d+(\\.\\d+){3}'");
            $gateway = trim($gwResult->output()) ?: '192.168.100.1';

            $netmask = '255.255.254.0'; // Default
        }

        // Get DNS from current system
        $dnsResult = Process::path('/tmp')->run("grep nameserver /etc/resolv.conf | awk '{print $2}' | head -2");
        $dnsServers = array_filter(explode("\n", trim($dnsResult->output())));
        $dns = implode(';', $dnsServers) ?: '8.8.8.8;8.8.4.4';

        return [
            'ip' => $ip,
            'gateway' => $gateway ?: $this->deriveGateway($ip),
            'netmask' => $netmask,
            'dns' => $dns,
            'from_cmdline' => $fromCmdline,
        ];
    }

    private function deriveGateway(string $ip): string
    {
        // Derive gateway by replacing last octet with .1
        $parts = explode('.', $ip);
        $parts[3] = '1';

        return implode('.', $parts);
    }

    private function removeCmdlineIpOverride(): void
    {
        Process::path('/tmp')->run("sudo sed -i 's/ ip=[^ ]*//' ".self::CMDLINE_PATH);
    }

    private function runUpdate(): ?string
    {
        if (! file_exists(self::UPDATE_SCRIPT)) {
            return null;
        }

        info('Updating to latest version (this may take a few minutes)...');
        info('');

        $result = Process::path('/tmp')
            ->timeout(600)
            ->tty()
            ->run('sudo '.self::UPDATE_SCRIPT);

        info('');

        if (! $result->successful()) {
            return null;
        }

        // Extract version from update output
        if (preg_match('/Version:\s*(\S+)/', $result->output(), $matches)) {
            return $matches[1];
        }

        return 'updated';
    }

    private function rebootWithCountdown(SystemConfigService $systemConfig, int $seconds): void
    {
        info("Rebooting in {$seconds} seconds... (Ctrl+C to cancel)");

        for ($i = $seconds; $i > 0; $i--) {
            $this->output->write("\r{$i}...");
            sleep(1);
        }

        $this->output->writeln('');
        info('Rebooting now...');

        $systemConfig->reboot();
    }
}

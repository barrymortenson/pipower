<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class SetupSdCommand extends Command
{
    protected $signature = 'power-setup:sd-card';

    protected $description = 'Configure SD card with static IP for Raspberry Pi';

    public function handle(): int
    {
        info('UPS Monitor SD Card Setup');
        info('==========================');
        info('');
        info('This command configures static IP on an SD card that was');
        info('already flashed with Raspberry Pi Imager (hostname, user,');
        info('password, and SSH should be configured there first).');

        $bootfsPath = env('SD_BOOTFS_PATH', '/Volumes/bootfs');

        // Validate mount path
        if (! $this->validatePath($bootfsPath)) {
            return Command::FAILURE;
        }

        // Gather configuration
        $config = $this->gatherConfiguration();

        // Show summary
        $this->showSummary($config, $bootfsPath);

        if (! confirm('Proceed with SD card configuration?', default: true)) {
            warning('Aborted.');

            return Command::FAILURE;
        }

        // Apply configuration
        $success = true;

        $success = $this->configureCmdline($bootfsPath, $config) && $success;
        $success = $this->createFirstRunScript($bootfsPath, $config) && $success;
        $this->writeEnvToBootPartition($bootfsPath);

        if ($success) {
            info('');
            info('SD card configured successfully!');
            info('');
            info('Next steps:');
            info('  1. Safely eject the SD card');
            info('  2. Insert into Raspberry Pi and boot');
            info("  3. SSH: ssh pi@{$config['ip']}");
            info('  4. Run: sudo /boot/firmware/pipower-setup.sh');
            info('');
            info('Step 4 configures DNS via NetworkManager.');

            return Command::SUCCESS;
        }

        error('Some configuration steps failed. Check messages above.');

        return Command::FAILURE;
    }

    private function validatePath(string $bootfsPath): bool
    {
        if (! File::isDirectory($bootfsPath)) {
            error("Boot partition not found: {$bootfsPath}");
            error('Set SD_BOOTFS_PATH in .env to your mounted boot partition.');

            return false;
        }

        $cmdlinePath = "{$bootfsPath}/cmdline.txt";
        if (! File::exists($cmdlinePath)) {
            error("cmdline.txt not found at: {$cmdlinePath}");
            error('Ensure this is a valid Raspberry Pi OS boot partition.');

            return false;
        }

        return true;
    }

    private function gatherConfiguration(): array
    {
        info('');
        info('Network Configuration');
        info('---------------------');

        $ip = text(
            label: 'Static IP address:',
            default: '192.168.100.40',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_IP)
                ? null
                : 'Please enter a valid IP address.'
        );

        $netmask = text(
            label: 'Subnet mask:',
            default: '255.255.254.0',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_IP)
                ? null
                : 'Please enter a valid subnet mask.'
        );

        $gateway = text(
            label: 'Gateway:',
            default: '192.168.100.1',
            required: true,
            validate: fn (string $value) => filter_var($value, FILTER_VALIDATE_IP)
                ? null
                : 'Please enter a valid gateway IP address.'
        );

        return [
            'ip' => $ip,
            'netmask' => $netmask,
            'gateway' => $gateway,
            'dns' => ['8.8.8.8', '8.8.4.4'],
        ];
    }

    private function showSummary(array $config, string $bootfsPath): void
    {
        info('');
        info('Configuration Summary');
        info('---------------------');
        info("Static IP:     {$config['ip']}");
        info("Subnet mask:   {$config['netmask']}");
        info("Gateway:       {$config['gateway']}");
        info('DNS servers:   '.implode(', ', $config['dns']));
        info('');
        info("Boot partition: {$bootfsPath}");

        $envValues = $this->getEnvKeysToExport();
        if (! empty($envValues)) {
            info('');
            info('Notification settings to copy:');
            foreach ($envValues as $key => $value) {
                $display = $this->shouldMask($key) ? $this->maskValue($value) : $value;
                note("  {$key}={$display}");
            }
        }

        info('');
    }

    private function configureCmdline(string $bootfsPath, array $config): bool
    {
        $cmdlinePath = "{$bootfsPath}/cmdline.txt";

        $content = File::get($cmdlinePath);
        $ipParam = "ip={$config['ip']}::{$config['gateway']}:{$config['netmask']}:rpi:eth0:off";

        // Check if IP param already exists
        if (str_contains($content, 'ip=')) {
            // Replace existing IP parameter
            $content = preg_replace('/ip=[^\s]+/', $ipParam, $content);
            info('Updated existing IP configuration in cmdline.txt');
        } else {
            // Insert after rootwait
            if (! str_contains($content, 'rootwait')) {
                error('cmdline.txt does not contain "rootwait" - cannot insert IP configuration.');

                return false;
            }
            $content = str_replace('rootwait', "rootwait {$ipParam}", $content);
            info('Added IP configuration to cmdline.txt');
        }

        File::put($cmdlinePath, $content);

        return true;
    }

    private function createFirstRunScript(string $bootfsPath, array $config): bool
    {
        $scriptPath = "{$bootfsPath}/pipower-setup.sh";
        $dnsServers = implode(';', $config['dns']);
        $ip = $config['ip'];
        $gateway = $config['gateway'];

        // Calculate prefix length from netmask
        $prefixLength = $this->netmaskToPrefix($config['netmask']);

        $content = <<<BASH
#!/bin/bash
# Network setup script
# Run this after first boot: sudo /boot/firmware/pipower-setup.sh

set -e

TARGET_IP="{$ip}"

echo "Configuring static IP with DNS..."
echo ""

# Get current IP
CURRENT_IP=\$(hostname -I | awk '{print \$1}')

# Remove any existing static config
rm -f /etc/NetworkManager/system-connections/ethernet-static.nmconnection 2>/dev/null || true

# Create NetworkManager connection for eth0 with static IP and DNS
cat > /etc/NetworkManager/system-connections/ethernet-static.nmconnection << 'NMCONFIG'
[connection]
id=ethernet-static
type=ethernet
interface-name=eth0
autoconnect=true
autoconnect-priority=100

[ethernet]

[ipv4]
method=manual
addresses={$ip}/{$prefixLength};
gateway={$gateway}
dns={$dnsServers};
dns-search=

[ipv6]
method=disabled
NMCONFIG

# Set correct permissions (required by NetworkManager)
chmod 600 /etc/NetworkManager/system-connections/ethernet-static.nmconnection

# Reload configs
nmcli connection reload

echo "Static IP: {$ip}/{$prefixLength}"
echo "Gateway: {$gateway}"
echo "DNS: {$dnsServers}"
echo ""

if [ "\$CURRENT_IP" = "\$TARGET_IP" ]; then
    echo "IP unchanged. Activating static configuration..."
else
    echo "IP changing from \$CURRENT_IP to \$TARGET_IP"
fi

echo "Network will briefly disconnect. Reconnect: ssh pi@{$ip}"
echo ""

# Remove IP from cmdline.txt so NetworkManager controls IP going forward
# This allows IP changes from the web UI
if grep -q 'ip=' /boot/firmware/cmdline.txt; then
    sed -i 's/ ip=[^ ]*//' /boot/firmware/cmdline.txt
    echo "Removed boot-time IP from cmdline.txt (NetworkManager now controls IP)"
fi

# Always background the activation - even same IP causes brief network blip
nohup bash -c 'sleep 1; nmcli connection up ethernet-static 2>/dev/null' &>/dev/null &

echo "Static IP configured successfully."
BASH;

        File::put($scriptPath, $content);
        chmod($scriptPath, 0755);
        info('Created pipower-setup.sh (run after first boot for DNS)');

        return true;
    }

    private function netmaskToPrefix(string $netmask): int
    {
        $long = ip2long($netmask);
        $base = ip2long('255.255.255.255');

        return 32 - (int) log(($long ^ $base) + 1, 2);
    }

    private const ENV_KEYS = [
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_ENCRYPTION',
        'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME',
        'PLIVO_AUTH_ID',
        'PLIVO_AUTH_TOKEN',
        'PLIVO_FROM_NUMBER',
        'DEFAULT_RECIPIENTS',
    ];

    private const MASKED_KEYS = [
        'MAIL_PASSWORD',
        'PLIVO_AUTH_ID',
        'PLIVO_AUTH_TOKEN',
    ];

    private function getEnvKeysToExport(): array
    {
        $values = [];

        foreach (self::ENV_KEYS as $key) {
            $value = env($key);
            if ($value !== null && $value !== '') {
                $values[$key] = (string) $value;
            }
        }

        return $values;
    }

    private function shouldMask(string $key): bool
    {
        return in_array($key, self::MASKED_KEYS, true);
    }

    private function maskValue(string $value): string
    {
        if (strlen($value) <= 4) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 2).str_repeat('*', strlen($value) - 4).substr($value, -2);
    }

    private function writeEnvToBootPartition(string $bootfsPath): void
    {
        $envValues = $this->getEnvKeysToExport();

        if (empty($envValues)) {
            info('No notification settings found in .env — skipping pipower.env');

            return;
        }

        $lines = ['# PiPower notification settings (written by power-setup:sd-card)'];
        foreach ($envValues as $key => $value) {
            $lines[] = "{$key}={$value}";
        }

        $envPath = "{$bootfsPath}/pipower.env";
        File::put($envPath, implode("\n", $lines)."\n");
        info('Wrote notification settings to pipower.env on boot partition');
    }
}

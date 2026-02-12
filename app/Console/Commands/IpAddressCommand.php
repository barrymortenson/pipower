<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\SystemConfigService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

class IpAddressCommand extends Command
{
    protected $signature = 'power:ip-address';

    protected $description = 'Configure static IP address settings';

    public function handle(SystemConfigService $systemConfig): int
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            error('This command must be run on the Raspberry Pi.');

            return Command::FAILURE;
        }

        $current = $systemConfig->getCurrentNetworkConfig();

        info('');
        info('Current Network Configuration');
        info('-----------------------------');
        info("IP Address: {$current['ip']}");
        info("Gateway:    {$current['gateway']}");
        info("Netmask:    {$current['netmask']}");
        info("DNS:        {$current['dns']}");
        info('');

        $ip = text(
            label: 'Static IP address:',
            default: $current['ip'],
            required: true,
            validate: fn ($v) => filter_var($v, FILTER_VALIDATE_IP) ? null : 'Enter a valid IP address'
        );

        $gateway = text(
            label: 'Gateway:',
            default: $current['gateway'],
            required: true,
            validate: fn ($v) => filter_var($v, FILTER_VALIDATE_IP) ? null : 'Enter a valid IP address'
        );

        $netmask = text(
            label: 'Netmask:',
            default: $current['netmask'],
            required: true,
            validate: fn ($v) => filter_var($v, FILTER_VALIDATE_IP) ? null : 'Enter a valid netmask'
        );

        $dns = text(
            label: 'DNS servers:',
            default: $current['dns'],
            hint: 'Separate multiple servers with semicolons (e.g., 8.8.8.8;8.8.4.4)',
            required: true,
            validate: function ($v) {
                $servers = explode(';', $v);
                foreach ($servers as $server) {
                    if (! filter_var(trim($server), FILTER_VALIDATE_IP)) {
                        return 'Enter valid IP addresses separated by semicolons';
                    }
                }

                return null;
            }
        );

        // Check if anything changed
        if ($ip === $current['ip'] && $gateway === $current['gateway'] &&
            $netmask === $current['netmask'] && $dns === $current['dns']) {
            info('');
            info('Network configuration unchanged.');

            return Command::SUCCESS;
        }

        info('');
        info('New Configuration');
        info('-----------------');
        info("IP Address: {$ip}");
        info("Gateway:    {$gateway}");
        info("Netmask:    {$netmask}");
        info("DNS:        {$dns}");
        info('');

        if (! confirm('Apply these settings?', default: true)) {
            info('Cancelled.');

            return Command::SUCCESS;
        }

        info('Applying network configuration...');

        if (! $systemConfig->configureNetwork($ip, $gateway, $netmask, $dns)) {
            error('Failed to apply network configuration.');

            return Command::FAILURE;
        }

        // Save to database for web UI consistency
        Setting::set('static_ip', $ip);
        Setting::set('static_gateway', $gateway);
        Setting::set('static_dns_primary', explode(';', $dns)[0] ?? null);
        Setting::set('static_dns_secondary', explode(';', $dns)[1] ?? null);

        info('Network configuration applied.');
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

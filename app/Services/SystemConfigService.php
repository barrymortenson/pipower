<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class SystemConfigService
{
    /**
     * Apply a new hostname to the system.
     * Updates /etc/hostname, sets transient hostname, and updates /etc/hosts.
     */
    public function applyHostname(string $hostname): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return true;
        }

        // Write directly to /etc/hostname (hostnamectl via D-Bus doesn't work through PHP)
        $tempFile = '/tmp/hostname';
        if (file_put_contents($tempFile, $hostname."\n") === false) {
            return false;
        }

        $result = Process::path('/tmp')->run("sudo /bin/cp {$tempFile} /etc/hostname");
        @unlink($tempFile);

        if (! $result->successful()) {
            return false;
        }

        // Sync filesystem to ensure hostname is written to disk before any reboot
        Process::path('/tmp')->run('sync');

        // Set transient hostname immediately (so it takes effect before reboot)
        Process::path('/tmp')->run("sudo /bin/hostname {$hostname}");

        // Update /etc/hosts
        return $this->updateHostsFile($hostname);
    }

    /**
     * Update /etc/hosts with the new hostname.
     */
    private function updateHostsFile(string $hostname): bool
    {
        $hostsContent = @file_get_contents('/etc/hosts');
        if ($hostsContent === false) {
            return false;
        }

        // Replace existing 127.0.1.1 entry or add one
        if (preg_match('/127\.0\.1\.1/', $hostsContent)) {
            $hostsContent = preg_replace('/127\.0\.1\.1\s+.*$/m', "127.0.1.1\t{$hostname}", $hostsContent);
        } else {
            $hostsContent .= "\n127.0.1.1\t{$hostname}\n";
        }

        $tempFile = '/tmp/hosts';
        if (file_put_contents($tempFile, $hostsContent) === false) {
            return false;
        }

        $result = Process::path('/tmp')->run("sudo /bin/cp {$tempFile} /etc/hosts");
        @unlink($tempFile);

        // Sync filesystem to ensure hosts is written to disk
        Process::path('/tmp')->run('sync');

        return $result->successful();
    }

    /**
     * Get the current system hostname.
     */
    public function getCurrentHostname(): string
    {
        return gethostname() ?: 'unknown';
    }

    /**
     * Configure network with static IP settings.
     */
    public function configureNetwork(string $ip, string $gateway, string $netmask, string $dns): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return true;
        }

        $prefix = $this->netmaskToPrefix($netmask);

        $nmConfig = <<<NMCONFIG
[connection]
id=ethernet-static
type=ethernet
interface-name=eth0
autoconnect=true
autoconnect-priority=100

[ethernet]

[ipv4]
method=manual
addresses={$ip}/{$prefix};
gateway={$gateway}
dns={$dns};
dns-search=

[ipv6]
method=disabled
NMCONFIG;

        $tempFile = '/tmp/ethernet-static.nmconnection';
        if (file_put_contents($tempFile, $nmConfig) === false) {
            return false;
        }

        Process::path('/tmp')->run("sudo /bin/cp {$tempFile} /etc/NetworkManager/system-connections/ethernet-static.nmconnection");
        Process::path('/tmp')->run('sudo /usr/bin/chmod 600 /etc/NetworkManager/system-connections/ethernet-static.nmconnection');
        $result = Process::path('/tmp')->run('sudo /usr/bin/nmcli connection reload');

        @unlink($tempFile);

        return $result->successful();
    }

    /**
     * Get current network configuration.
     */
    public function getCurrentNetworkConfig(): array
    {
        $ip = null;
        $gateway = null;
        $netmask = '255.255.254.0';
        $dns = '8.8.8.8;8.8.4.4';

        if (PHP_OS_FAMILY === 'Linux') {
            // Get IP address
            $ipResult = Process::path('/tmp')->run("ip -4 addr show eth0 | grep -oP '(?<=inet\\s)\\d+(\\.\\d+){3}'");
            $ip = trim($ipResult->output()) ?: null;

            // Get default gateway
            $gwResult = Process::path('/tmp')->run("ip route | grep default | grep -oP '(?<=via\\s)\\d+(\\.\\d+){3}'");
            $gateway = trim($gwResult->output()) ?: null;

            // Get DNS servers from resolv.conf
            $dnsResult = Process::path('/tmp')->run("grep nameserver /etc/resolv.conf | awk '{print $2}' | head -2");
            $dnsServers = array_filter(explode("\n", trim($dnsResult->output())));
            $dns = implode(';', $dnsServers) ?: '8.8.8.8;8.8.4.4';
        }

        return [
            'ip' => $ip ?? '192.168.1.100',
            'gateway' => $gateway ?? '192.168.1.1',
            'netmask' => $netmask,
            'dns' => $dns,
        ];
    }

    /**
     * Convert netmask to CIDR prefix.
     */
    public function netmaskToPrefix(string $netmask): int
    {
        $long = ip2long($netmask);
        $base = ip2long('255.255.255.255');

        return 32 - (int) log(($long ^ $base) + 1, 2);
    }

    /**
     * Reboot the system.
     */
    public function reboot(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return;
        }

        // Sync filesystem before rebooting to ensure all changes are written
        Process::path('/tmp')->run('sync');

        Process::path('/tmp')->run('sudo /sbin/reboot');
    }
}

<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Process;

class NetworkService
{
    public function getNetworkStatus(): array
    {
        $ethernet = $this->checkEthernet();

        return [
            'ethernet' => $ethernet,
            'hostname' => Setting::get('hostname') ?: gethostname() ?: 'UPS Monitor',
        ];
    }

    private function checkEthernet(): array
    {
        $result = Process::path('/tmp')->run('cat /sys/class/net/eth0/carrier 2>/dev/null');
        $connected = trim($result->output()) === '1';

        $ip = null;
        $gateway = null;
        $dnsPrimary = null;
        $dnsSecondary = null;

        if ($connected) {
            // Get IP address
            $ipResult = Process::path('/tmp')->run("ip -4 addr show eth0 | grep -oP '(?<=inet\\s)\\d+(\\.\\d+){3}'");
            $ip = trim($ipResult->output()) ?: null;

            // Get default gateway
            $gwResult = Process::path('/tmp')->run("ip route | grep default | grep -oP '(?<=via\\s)\\d+(\\.\\d+){3}'");
            $gateway = trim($gwResult->output()) ?: null;

            // Get DNS servers from resolv.conf
            $dnsResult = Process::path('/tmp')->run("grep nameserver /etc/resolv.conf | awk '{print $2}'");
            $dnsServers = array_filter(explode("\n", trim($dnsResult->output())));
            $dnsPrimary = $dnsServers[0] ?? null;
            $dnsSecondary = $dnsServers[1] ?? null;
        }

        return [
            'connected' => $connected,
            'ip' => $ip,
            'gateway' => $gateway,
            'dns_primary' => $dnsPrimary,
            'dns_secondary' => $dnsSecondary,
        ];
    }
}

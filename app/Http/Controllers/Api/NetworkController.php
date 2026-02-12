<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\NetworkService;
use App\Services\SystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Process;

class NetworkController extends Controller
{
    public function __construct(
        private NetworkService $networkService,
        private SystemConfigService $systemConfig
    ) {}

    public function status(): JsonResponse
    {
        return response()->json($this->networkService->getNetworkStatus());
    }

    public function applyAndReboot(): JsonResponse
    {
        // Only run on Linux (Raspberry Pi)
        if (PHP_OS_FAMILY !== 'Linux') {
            return response()->json(['success' => false, 'message' => 'Only available on Raspberry Pi']);
        }

        $hostname = Setting::get('hostname');
        $staticIp = Setting::get('static_ip');
        $gateway = Setting::get('static_gateway');
        $dnsPrimary = Setting::get('static_dns_primary');
        $dnsSecondary = Setting::get('static_dns_secondary');

        // Apply hostname
        if ($hostname) {
            $this->systemConfig->applyHostname($hostname);
        }

        // Apply static IP configuration if all required fields are present
        if ($staticIp && $gateway && $dnsPrimary) {
            $dns = $dnsSecondary ? "{$dnsPrimary};{$dnsSecondary}" : $dnsPrimary;
            $this->systemConfig->configureNetwork($staticIp, $gateway, '255.255.254.0', $dns);
        }

        // Trigger reboot in background (give time for response to be sent)
        Process::path('/tmp')->run('nohup bash -c "sleep 2; sudo /sbin/reboot" &>/dev/null &');

        return response()->json(['success' => true]);
    }
}

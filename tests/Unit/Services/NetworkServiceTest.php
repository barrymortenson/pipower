<?php

use App\Models\Setting;
use App\Services\NetworkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

uses(RefreshDatabase::class);

it('returns ethernet connected with ip gateway and dns', function () {
    Process::fake([
        '*cat /sys/class/net/eth0/carrier*' => Process::result(output: '1', exitCode: 0),
        '*ip -4 addr show eth0*' => Process::result(output: '192.168.1.100', exitCode: 0),
        '*ip route*' => Process::result(output: '192.168.1.1', exitCode: 0),
        '*grep nameserver*' => Process::result(output: "8.8.8.8\n8.8.4.4", exitCode: 0),
    ]);

    $service = new NetworkService;
    $status = $service->getNetworkStatus();

    expect($status['ethernet']['connected'])->toBeTrue()
        ->and($status['ethernet']['ip'])->toBe('192.168.1.100')
        ->and($status['ethernet']['gateway'])->toBe('192.168.1.1')
        ->and($status['ethernet']['dns_primary'])->toBe('8.8.8.8')
        ->and($status['ethernet']['dns_secondary'])->toBe('8.8.4.4');
});

it('returns ethernet disconnected when carrier is 0', function () {
    Process::fake([
        '*cat /sys/class/net/eth0/carrier*' => Process::result(output: '0', exitCode: 0),
    ]);

    $service = new NetworkService;
    $status = $service->getNetworkStatus();

    expect($status['ethernet']['connected'])->toBeFalse()
        ->and($status['ethernet']['ip'])->toBeNull()
        ->and($status['ethernet']['gateway'])->toBeNull()
        ->and($status['ethernet']['dns_primary'])->toBeNull()
        ->and($status['ethernet']['dns_secondary'])->toBeNull();
});

it('returns hostname from setting', function () {
    Process::fake([
        '*cat /sys/class/net/eth0/carrier*' => Process::result(output: '0', exitCode: 0),
    ]);

    Setting::set('hostname', 'my-pipower');

    $service = new NetworkService;
    $status = $service->getNetworkStatus();

    expect($status['hostname'])->toBe('my-pipower');
});

it('falls back to gethostname when setting is empty', function () {
    Process::fake([
        '*cat /sys/class/net/eth0/carrier*' => Process::result(output: '0', exitCode: 0),
    ]);

    $service = new NetworkService;
    $status = $service->getNetworkStatus();

    expect($status['hostname'])->not->toBeEmpty();
});

it('handles missing dns servers', function () {
    Process::fake([
        '*cat /sys/class/net/eth0/carrier*' => Process::result(output: '1', exitCode: 0),
        '*ip -4 addr show eth0*' => Process::result(output: '192.168.1.100', exitCode: 0),
        '*ip route*' => Process::result(output: '192.168.1.1', exitCode: 0),
        '*grep nameserver*' => Process::result(output: '', exitCode: 1),
    ]);

    $service = new NetworkService;
    $status = $service->getNetworkStatus();

    expect($status['ethernet']['connected'])->toBeTrue()
        ->and($status['ethernet']['dns_primary'])->toBeNull()
        ->and($status['ethernet']['dns_secondary'])->toBeNull();
});

it('handles single dns server', function () {
    Process::fake([
        '*cat /sys/class/net/eth0/carrier*' => Process::result(output: '1', exitCode: 0),
        '*ip -4 addr show eth0*' => Process::result(output: '192.168.1.100', exitCode: 0),
        '*ip route*' => Process::result(output: '192.168.1.1', exitCode: 0),
        '*grep nameserver*' => Process::result(output: '8.8.8.8', exitCode: 0),
    ]);

    $service = new NetworkService;
    $status = $service->getNetworkStatus();

    expect($status['ethernet']['dns_primary'])->toBe('8.8.8.8')
        ->and($status['ethernet']['dns_secondary'])->toBeNull();
});

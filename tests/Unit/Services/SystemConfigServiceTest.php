<?php

use App\Services\SystemConfigService;

it('converts common netmasks to prefix', function (string $netmask, int $expected) {
    $service = new SystemConfigService;

    expect($service->netmaskToPrefix($netmask))->toBe($expected);
})->with([
    '255.255.255.0 -> /24' => ['255.255.255.0', 24],
    '255.255.254.0 -> /23' => ['255.255.254.0', 23],
    '255.255.0.0 -> /16' => ['255.255.0.0', 16],
    '255.0.0.0 -> /8' => ['255.0.0.0', 8],
    '255.255.255.128 -> /25' => ['255.255.255.128', 25],
    '255.255.255.252 -> /30' => ['255.255.255.252', 30],
]);

it('getCurrentHostname returns non-empty string', function () {
    $service = new SystemConfigService;

    $hostname = $service->getCurrentHostname();

    expect($hostname)->toBeString()->not->toBeEmpty();
});

it('applyHostname returns true on non-linux', function () {
    // On macOS (darwin), this should return true immediately
    if (PHP_OS_FAMILY === 'Linux') {
        $this->markTestSkipped('Only runs on non-Linux');
    }

    $service = new SystemConfigService;

    expect($service->applyHostname('test-hostname'))->toBeTrue();
});

it('configureNetwork returns true on non-linux', function () {
    if (PHP_OS_FAMILY === 'Linux') {
        $this->markTestSkipped('Only runs on non-Linux');
    }

    $service = new SystemConfigService;

    expect($service->configureNetwork('192.168.1.100', '192.168.1.1', '255.255.255.0', '8.8.8.8'))->toBeTrue();
});

it('reboot does nothing on non-linux', function () {
    if (PHP_OS_FAMILY === 'Linux') {
        $this->markTestSkipped('Only runs on non-Linux');
    }

    $service = new SystemConfigService;

    // Should not throw or do anything harmful
    $service->reboot();

    expect(true)->toBeTrue();
});

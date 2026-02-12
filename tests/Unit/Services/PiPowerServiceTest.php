<?php

use App\Models\Event;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\NutService;
use App\Services\PiPowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Helper to create a PiPowerService with mocked dependencies
function makePiPowerService(array $healthCheck, ?array $status, ?Mockery\MockInterface $notificationMock = null): PiPowerService
{
    $nutService = Mockery::mock(NutService::class);
    $nutService->shouldReceive('healthCheck')->andReturn($healthCheck);
    $nutService->shouldReceive('getStatus')->andReturn($status);

    $notificationService = $notificationMock ?? Mockery::mock(NotificationService::class);

    return new PiPowerService($nutService, $notificationService);
}

// --- Existing tests ---

it('detects power lost event', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('power_lost', Mockery::any());

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => 3600],
        $notificationService
    );

    Setting::set('last_power_state', 'on_mains');

    $service->checkAndNotify();

    expect(Event::where('type', 'power_lost')->count())->toBe(1);
});

it('detects power restored event', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('power_restored', Mockery::any());

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => false, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => 3600],
        $notificationService
    );

    Setting::set('last_power_state', 'on_battery');

    $service->checkAndNotify();

    expect(Event::where('type', 'power_restored')->count())->toBe(1);
});

it('does not notify when state unchanged', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldNotReceive('notify');

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => false, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => 3600],
        $notificationService
    );

    Setting::set('last_power_state', 'on_mains');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);

    $service->checkAndNotify();

    expect(Event::count())->toBe(0);
});

// --- checkUpsConnection() tests ---

it('detects ups disconnect transition', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('ups_disconnected', Mockery::any());

    Setting::set('last_ups_connected', true);

    $service = makePiPowerService(
        ['healthy' => false, 'error' => 'NUT daemon not running'],
        null,
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::where('type', 'ups_disconnected')->count())->toBe(1);
});

it('detects ups reconnect transition', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('ups_reconnected', Mockery::any());

    Setting::set('last_ups_connected', false);
    Setting::set('last_power_state', 'on_mains');
    Setting::set('last_battery_low', false);

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => false, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => 3600],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::where('type', 'ups_reconnected')->count())->toBe(1);
});

it('does not notify when ups stays disconnected', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldNotReceive('notify');

    Setting::set('last_ups_connected', false);

    $service = makePiPowerService(
        ['healthy' => false, 'error' => 'NUT daemon not running'],
        null,
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::count())->toBe(0);
});

// --- checkBatteryLow() tests ---

it('detects battery low transition', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('battery_low', Mockery::any());

    Setting::set('last_power_state', 'on_battery');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);
    Setting::set('runtime_critical_notified', true); // Prevent runtime_critical from firing

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => true, 'battery_level' => 5, 'battery_runtime' => 900],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::where('type', 'battery_low')->count())->toBe(1);
});

it('does not re-notify when battery already low', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldNotReceive('notify');

    Setting::set('last_power_state', 'on_battery');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', true);
    Setting::set('runtime_critical_notified', true); // Prevent runtime_critical from firing

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => true, 'battery_level' => 5, 'battery_runtime' => 900],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::count())->toBe(0);
});

// --- checkRuntimeCritical() tests ---

it('detects runtime critical when below threshold', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('runtime_critical', Mockery::any());

    Setting::set('last_power_state', 'on_battery');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);
    Setting::set('runtime_critical_notified', false);

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 20, 'battery_runtime' => 300],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::where('type', 'runtime_critical')->count())->toBe(1)
        ->and(Setting::get('runtime_critical_notified'))->toBeTrue();
});

it('does not re-notify runtime critical when already notified', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldNotReceive('notify');

    Setting::set('last_power_state', 'on_battery');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);
    Setting::set('runtime_critical_notified', true);

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 20, 'battery_runtime' => 300],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::count())->toBe(0);
});

it('skips runtime critical check when on mains', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldNotReceive('notify');

    Setting::set('last_power_state', 'on_mains');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => false, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => 300],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::count())->toBe(0);
});

it('skips runtime critical check when runtime is null', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldNotReceive('notify');

    Setting::set('last_power_state', 'on_battery');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 20],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Event::count())->toBe(0);
});

// --- getCurrentStatus() tests ---

it('getCurrentStatus returns connected structure', function () {
    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        [
            'connected' => true,
            'model' => 'CyberPower CP1500AVRLCD',
            'battery_level' => 100,
            'battery_low' => false,
            'battery_runtime' => 3600,
            'load_percent' => 15,
            'on_battery' => false,
        ]
    );

    $status = $service->getCurrentStatus();

    expect($status['ups_connected'])->toBeTrue()
        ->and($status['ups_error'])->toBeNull()
        ->and($status['power_status'])->toBe('on_mains')
        ->and($status['battery_level'])->toBe(100)
        ->and($status['battery_low'])->toBeFalse()
        ->and($status['model'])->toBe('CyberPower CP1500AVRLCD')
        ->and($status['load_percent'])->toBe(15)
        ->and($status['on_battery'])->toBeFalse();
});

it('getCurrentStatus returns disconnected defaults', function () {
    $service = makePiPowerService(
        ['healthy' => false, 'error' => 'NUT daemon not running'],
        null
    );

    $status = $service->getCurrentStatus();

    expect($status['ups_connected'])->toBeFalse()
        ->and($status['ups_error'])->toBe('NUT daemon not running')
        ->and($status['power_status'])->toBe('unknown')
        ->and($status['battery_level'])->toBeNull()
        ->and($status['model'])->toBeNull()
        ->and($status['on_battery'])->toBeFalse();
});

it('getCurrentStatus returns on_battery power status', function () {
    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        [
            'connected' => true,
            'model' => 'TestUPS',
            'battery_level' => 80,
            'battery_low' => false,
            'battery_runtime' => 1800,
            'load_percent' => 20,
            'on_battery' => true,
        ]
    );

    $status = $service->getCurrentStatus();

    expect($status['power_status'])->toBe('on_battery')
        ->and($status['on_battery'])->toBeTrue();
});

// --- Message formatting tests ---

it('power lost message includes runtime when available', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('power_lost', Mockery::on(function ($msg) {
        return str_contains($msg, 'remaining');
    }));

    Setting::set('last_power_state', 'on_mains');

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => 3600],
        $notificationService
    );

    $service->checkAndNotify();
});

it('power lost message without runtime', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('power_lost', Mockery::on(function ($msg) {
        return str_contains($msg, 'running on battery') && ! str_contains($msg, 'remaining');
    }));

    Setting::set('last_power_state', 'on_mains');

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => null],
        $notificationService
    );

    $service->checkAndNotify();
});

it('power restored message includes outage duration', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('power_restored', Mockery::on(function ($msg) {
        return str_contains($msg, 'restored after');
    }));

    Setting::set('last_power_state', 'on_battery');
    Setting::set('outage_started_at', now()->subMinutes(15)->toIso8601String());

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => false, 'battery_low' => false, 'battery_level' => 85, 'battery_runtime' => 3600],
        $notificationService
    );

    $service->checkAndNotify();
});

it('power restored message without outage duration', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('power_restored', Mockery::on(function ($msg) {
        return str_contains($msg, 'restored') && ! str_contains($msg, 'after');
    }));

    Setting::set('last_power_state', 'on_battery');
    // No outage_started_at set

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => false, 'battery_low' => false, 'battery_level' => 85, 'battery_runtime' => 3600],
        $notificationService
    );

    $service->checkAndNotify();
});

// --- State management tests ---

it('stores outage_started_at on power lost', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify');

    Setting::set('last_power_state', 'on_mains');

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => 3600],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Setting::get('outage_started_at'))->not->toBeNull();
});

it('clears outage_started_at on power restored', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify');

    Setting::set('last_power_state', 'on_battery');
    Setting::set('outage_started_at', now()->subMinutes(10)->toIso8601String());

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => false, 'battery_low' => false, 'battery_level' => 85, 'battery_runtime' => 3600],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Setting::get('outage_started_at'))->toBeNull();
});

it('clears runtime_critical_notified on power restored', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify');

    Setting::set('last_power_state', 'on_battery');
    Setting::set('runtime_critical_notified', true);

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => false, 'battery_low' => false, 'battery_level' => 85, 'battery_runtime' => 3600],
        $notificationService
    );

    $service->checkAndNotify();

    expect(Setting::get('runtime_critical_notified'))->toBeFalse();
});

it('runtime critical message includes formatted time', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('runtime_critical', Mockery::on(function ($msg) {
        return str_contains($msg, '5 min');
    }));

    Setting::set('last_power_state', 'on_battery');
    Setting::set('last_ups_connected', true);
    Setting::set('last_battery_low', false);
    Setting::set('runtime_critical_notified', false);

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 20, 'battery_runtime' => 300],
        $notificationService
    );

    $service->checkAndNotify();
});

it('formats runtime with hours and minutes', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    $notificationService->shouldReceive('notify')->once()->with('power_lost', Mockery::on(function ($msg) {
        return str_contains($msg, '1h 30m');
    }));

    Setting::set('last_power_state', 'on_mains');

    $service = makePiPowerService(
        ['healthy' => true, 'error' => null],
        ['connected' => true, 'on_battery' => true, 'battery_low' => false, 'battery_level' => 100, 'battery_runtime' => 5400],
        $notificationService
    );

    $service->checkAndNotify();
});

it('early returns when health check fails and status is null', function () {
    $notificationService = Mockery::mock(NotificationService::class);
    // Only ups_disconnected should fire, no power state checks
    $notificationService->shouldReceive('notify')->once()->with('ups_disconnected', Mockery::any());

    Setting::set('last_ups_connected', true);

    $service = makePiPowerService(
        ['healthy' => false, 'error' => 'NUT not running'],
        null,
        $notificationService
    );

    $service->checkAndNotify();

    // Only the disconnect event, no power state events
    expect(Event::count())->toBe(1)
        ->and(Event::first()->type)->toBe('ups_disconnected');
});

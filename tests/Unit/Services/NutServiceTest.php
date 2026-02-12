<?php

use App\Services\NutService;
use Illuminate\Support\Facades\Process;

it('parses ups status output', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 100
battery.charge.low: 10
ups.status: OL
ups.load: 15
device.model: CyberPower CP1500AVRLCD
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['battery_level'])->toBe(100)
        ->and($status['battery_low'])->toBeFalse()
        ->and($status['on_battery'])->toBeFalse()
        ->and($status['load_percent'])->toBe(15)
        ->and($status['model'])->toBe('CyberPower CP1500AVRLCD');
});

it('detects on battery status', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 85
battery.charge.low: 10
ups.status: OB
ups.load: 20
device.model: CyberPower CP1500AVRLCD
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['on_battery'])->toBeTrue()
        ->and($status['battery_level'])->toBe(85);
});

it('detects low battery', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 8
battery.charge.low: 10
ups.status: OB LB
ups.load: 20
device.model: CyberPower CP1500AVRLCD
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['battery_low'])->toBeTrue();
});

// --- getStatus() tests ---

it('getStatus returns null on command failure', function () {
    Process::fake([
        '*upsc*' => Process::result(output: '', exitCode: 1),
    ]);

    $nutService = new NutService;
    $status = $nutService->getStatus();

    expect($status)->toBeNull();
});

it('getStatus returns parsed status on success', function () {
    $rawOutput = <<<'EOT'
battery.charge: 95
battery.charge.low: 10
ups.status: OL
ups.load: 12
device.model: TestUPS
EOT;

    Process::fake([
        '*upsc*' => Process::result(output: $rawOutput, exitCode: 0),
    ]);

    $nutService = new NutService;
    $status = $nutService->getStatus();

    expect($status)->not->toBeNull()
        ->and($status['battery_level'])->toBe(95)
        ->and($status['model'])->toBe('TestUPS')
        ->and($status['connected'])->toBeTrue();
});

// --- healthCheck() tests ---

it('healthCheck detects connection refused', function () {
    Process::fake([
        '*upsc*' => Process::result(output: 'Connection refused', exitCode: 1),
    ]);

    $nutService = new NutService;
    $health = $nutService->healthCheck();

    expect($health['healthy'])->toBeFalse()
        ->and($health['error'])->toBe('NUT daemon not running');
});

it('healthCheck detects unknown ups', function () {
    Process::fake([
        '*upsc*' => Process::result(output: 'Unknown UPS [ups@localhost]', exitCode: 1),
    ]);

    $nutService = new NutService;
    $health = $nutService->healthCheck();

    expect($health['healthy'])->toBeFalse()
        ->and($health['error'])->toContain('UPS not found');
});

it('healthCheck returns unknown error for other failures', function () {
    Process::fake([
        '*upsc*' => Process::result(output: 'Some weird error', exitCode: 1),
    ]);

    $nutService = new NutService;
    $health = $nutService->healthCheck();

    expect($health['healthy'])->toBeFalse()
        ->and($health['error'])->toContain('Unknown error');
});

it('healthCheck returns healthy on success', function () {
    Process::fake([
        '*upsc*' => Process::result(output: 'battery.charge: 100', exitCode: 0),
    ]);

    $nutService = new NutService;
    $health = $nutService->healthCheck();

    expect($health['healthy'])->toBeTrue()
        ->and($health['error'])->toBeNull();
});

// --- parseStatus() edge cases ---

it('parseStatus handles missing fields with defaults', function () {
    $nutService = new NutService;

    $rawOutput = "ups.status: OL\n";
    $status = $nutService->parseStatus($rawOutput);

    expect($status['battery_level'])->toBe(0)
        ->and($status['load_percent'])->toBe(0)
        ->and($status['model'])->toBe('Unknown')
        ->and($status['connected'])->toBeTrue();
});

it('parseStatus handles empty output', function () {
    $nutService = new NutService;

    $status = $nutService->parseStatus('');

    expect($status['connected'])->toBeTrue()
        ->and($status['battery_level'])->toBe(0)
        ->and($status['model'])->toBe('Unknown');
});

it('parseStatus falls back to ups.model when device.model missing', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 100
ups.status: OL
ups.model: FallbackModel
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['model'])->toBe('FallbackModel');
});

it('parseStatus prefers device.model over ups.model', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 100
ups.status: OL
device.model: PreferredModel
ups.model: FallbackModel
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['model'])->toBe('PreferredModel');
});

it('parseStatus detects charging status', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 50
ups.status: OL CHRG
ups.load: 10
device.model: TestUPS
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['charging'])->toBeTrue()
        ->and($status['on_battery'])->toBeFalse();
});

it('parseStatus parses battery runtime in seconds', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 100
battery.runtime: 3600
ups.status: OL
device.model: TestUPS
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['battery_runtime'])->toBe(3600);
});

it('parseStatus returns null battery runtime when not available', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 100
ups.status: OL
device.model: TestUPS
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['battery_runtime'])->toBeNull();
});

it('parseStatus detects battery low by threshold when LB flag absent', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 5
battery.charge.low: 10
ups.status: OB
device.model: TestUPS
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['battery_low'])->toBeTrue();
});

it('parseStatus returns raw status string', function () {
    $nutService = new NutService;

    $rawOutput = <<<'EOT'
battery.charge: 100
ups.status: OL CHRG
device.model: TestUPS
EOT;

    $status = $nutService->parseStatus($rawOutput);

    expect($status['raw_status'])->toBe('OL CHRG');
});

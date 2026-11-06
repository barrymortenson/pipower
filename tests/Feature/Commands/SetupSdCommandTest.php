<?php

use Illuminate\Support\Facades\File;

const SD_ENV_KEYS = [
    'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD',
    'MAIL_ENCRYPTION', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'PLIVO_AUTH_ID',
    'PLIVO_AUTH_TOKEN', 'PLIVO_FROM_NUMBER', 'DEFAULT_RECIPIENTS', 'SD_BOOTFS_PATH',
];

function setTestEnv(string $key, string $value): void
{
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}

beforeEach(function () {
    $this->savedEnv = [];
    foreach (SD_ENV_KEYS as $key) {
        $this->savedEnv[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        // Blank everything so the developer's local .env never leaks in
        setTestEnv($key, '');
    }

    $this->bootfs = sys_get_temp_dir().'/pipower-bootfs-'.uniqid();
    File::ensureDirectoryExists($this->bootfs);
    File::put("{$this->bootfs}/cmdline.txt", 'console=tty1 root=PARTUUID=abc rootwait');
    setTestEnv('SD_BOOTFS_PATH', $this->bootfs);
});

afterEach(function () {
    File::deleteDirectory($this->bootfs);

    foreach ($this->savedEnv as $key => [$env, $server, $put]) {
        if ($env === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $env;
        }
        if ($server === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $server;
        }
        putenv($put === false ? $key : "{$key}={$put}");
    }
});

function runSdSetup($test, ?bool $copyEnv)
{
    $command = $test->artisan('power-setup:sd-card')
        ->expectsQuestion('Static IP address:', '192.168.1.50')
        ->expectsQuestion('Subnet mask:', '255.255.255.0')
        ->expectsQuestion('Gateway:', '192.168.1.1');

    if ($copyEnv !== null) {
        $command->expectsConfirmation('Copy these notification settings to the SD card?', $copyEnv ? 'yes' : 'no');
    }

    return $command->expectsConfirmation('Proceed with SD card configuration?', 'yes');
}

it('does not copy notification settings unless confirmed', function () {
    setTestEnv('MAIL_HOST', 'smtp.private.test');
    setTestEnv('DEFAULT_RECIPIENTS', 'me@private.test');

    runSdSetup($this, copyEnv: false)
        ->expectsOutputToContain('DEFAULT_RECIPIENTS=me@private.test')
        ->assertSuccessful();

    expect(File::exists("{$this->bootfs}/pipower.env"))->toBeFalse();
});

it('copies notification settings when confirmed and masks secrets in the preview', function () {
    setTestEnv('MAIL_HOST', 'smtp.private.test');
    setTestEnv('MAIL_PASSWORD', 'hunter2-password');
    setTestEnv('DEFAULT_RECIPIENTS', 'me@private.test');

    runSdSetup($this, copyEnv: true)
        ->expectsOutputToContain('MAIL_PASSWORD=hu************rd')
        ->doesntExpectOutputToContain('hunter2-password')
        ->assertSuccessful();

    $contents = File::get("{$this->bootfs}/pipower.env");
    expect($contents)
        ->toContain('MAIL_HOST=smtp.private.test')
        ->toContain('MAIL_PASSWORD=hunter2-password')
        ->toContain('DEFAULT_RECIPIENTS=me@private.test');
});

it('removes a pipower.env left by an earlier run when not copying', function () {
    File::put("{$this->bootfs}/pipower.env", "DEFAULT_RECIPIENTS=old@private.test\n");
    setTestEnv('DEFAULT_RECIPIENTS', 'me@private.test');

    runSdSetup($this, copyEnv: false)->assertSuccessful();

    expect(File::exists("{$this->bootfs}/pipower.env"))->toBeFalse();
});

it('skips the copy prompt when .env has no notification settings', function () {
    runSdSetup($this, copyEnv: null)->assertSuccessful();

    expect(File::exists("{$this->bootfs}/pipower.env"))->toBeFalse();
});

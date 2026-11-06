<?php

use App\Models\Recipient;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Services\NotificationSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('leaves mail config untouched when nothing was saved in the UI', function () {
    config(['mail.default' => 'log', 'mail.mailers.smtp.host' => 'smtp.env.test']);

    (new NotificationSettingsService)->applyMailConfig();

    expect(config('mail.default'))->toBe('log')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.env.test');
});

it('applies saved smtp settings to the mail config', function () {
    config(['mail.default' => 'log']);

    $service = new NotificationSettingsService;
    $service->update([
        'smtp_host' => 'smtp.ui.test',
        'smtp_port' => 465,
        'smtp_username' => 'ui-user',
        'smtp_password' => 'ui-password',
        'smtp_from_address' => 'ui@example.com',
        'smtp_from_name' => 'UI Name',
    ]);
    $service->applyMailConfig();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.ui.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(465)
        ->and(config('mail.mailers.smtp.username'))->toBe('ui-user')
        ->and(config('mail.mailers.smtp.password'))->toBe('ui-password')
        ->and(config('mail.from.address'))->toBe('ui@example.com')
        ->and(config('mail.from.name'))->toBe('UI Name');
});

it('only overrides the smtp keys that were saved', function () {
    config(['mail.mailers.smtp.host' => 'smtp.env.test', 'mail.mailers.smtp.password' => 'env-password']);

    $service = new NotificationSettingsService;
    $service->update(['smtp_from_name' => 'UI Name']);
    $service->applyMailConfig();

    expect(config('mail.mailers.smtp.host'))->toBe('smtp.env.test')
        ->and(config('mail.mailers.smtp.password'))->toBe('env-password')
        ->and(config('mail.from.name'))->toBe('UI Name');
});

it('never sends the .env password to a different server saved in the UI', function () {
    config([
        'pipower.smtp.host' => 'smtp.env.test',
        'mail.mailers.smtp.host' => 'smtp.env.test',
        'mail.mailers.smtp.password' => 'env-password',
    ]);

    // Simulate a host row with no password row (e.g. written outside update())
    Setting::set('smtp_host', 'attacker.test');

    (new NotificationSettingsService)->applyMailConfig();

    expect(config('mail.mailers.smtp.host'))->toBe('attacker.test')
        ->and(config('mail.mailers.smtp.password'))->toBeNull();
});

it('treats an undecryptable secret as not set', function () {
    Setting::set('plivo_auth_token', 'not-valid-ciphertext');

    expect((new NotificationSettingsService)->plivo()['auth_token'])->toBeNull();
});

it('sends sms with plivo credentials saved in the UI over .env values', function () {
    Http::fake(['api.plivo.com/*' => Http::response(['message' => 'sent'], 202)]);

    config([
        'pipower.plivo.auth_id' => 'env_id',
        'pipower.plivo.auth_token' => 'env_token',
        'pipower.plivo.from_number' => '+15550000000',
    ]);

    (new NotificationSettingsService)->update([
        'plivo_auth_id' => 'ui_id',
        'plivo_auth_token' => 'ui_token',
        'plivo_from_number' => '+15559999999',
    ]);

    Recipient::create(['type' => 'phone', 'value' => '+15551234567', 'enabled' => true]);

    (new NotificationService)->sendSmsNotifications('host', 'Subject', 'Message');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/Account/ui_id/Message/')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('ui_id:ui_token'))
            && $request['src'] === '+15559999999';
    });
});

it('stores secrets encrypted', function () {
    (new NotificationSettingsService)->update(['smtp_password' => 'ui-password']);

    expect(Crypt::decryptString(Setting::get('smtp_password')))->toBe('ui-password');
});

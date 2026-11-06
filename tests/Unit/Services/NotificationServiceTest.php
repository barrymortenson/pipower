<?php

use App\Models\Recipient;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('sends email notifications to enabled recipients', function () {
    Mail::fake();

    Recipient::create(['email' => 'test@example.com', 'enabled' => true]);
    Recipient::create(['email' => 'disabled@example.com', 'enabled' => false]);

    $service = new NotificationService;
    $service->sendEmailNotifications('power_lost', 'Power Lost', 'The power has been lost.', 'test-host');

    Mail::assertSent(\App\Mail\PowerEventMail::class, 1);
});

it('sends sms notifications to enabled recipients', function () {
    Http::fake([
        'api.plivo.com/*' => Http::response(['message' => 'sent'], 200),
    ]);

    // Configure Plivo credentials for test
    config([
        'pipower.plivo.auth_id' => 'test_auth_id',
        'pipower.plivo.auth_token' => 'test_auth_token',
        'pipower.plivo.from_number' => '+15550000000',
    ]);

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $service = new NotificationService;
    $service->sendSmsNotifications('test-host', 'Power Lost', 'Running on battery');

    Http::assertSentCount(1);
});

// --- notify() orchestration tests ---

it('notify sends both email and sms', function () {
    Mail::fake();
    Http::fake([
        'api.plivo.com/*' => Http::response(['message' => 'sent'], 200),
    ]);

    config([
        'pipower.plivo.auth_id' => 'test_auth_id',
        'pipower.plivo.auth_token' => 'test_auth_token',
        'pipower.plivo.from_number' => '+15550000000',
    ]);

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);
    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('power_lost', 'Power outage detected');

    Mail::assertSent(\App\Mail\PowerEventMail::class, 1);
    Http::assertSentCount(1);
});

it('notify uses correct subject for power_lost', function () {
    Mail::fake();

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('power_lost', 'Power outage detected');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        return $mail->eventSubject === 'Power Outage';
    });
});

it('notify uses correct subject for power_restored', function () {
    Mail::fake();

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('power_restored', 'Power restored');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        return $mail->eventSubject === 'Power Restored';
    });
});

it('notify uses correct subject for battery_low', function () {
    Mail::fake();

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('battery_low', 'Battery critical');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        return $mail->eventSubject === 'Battery Critical';
    });
});

it('notify uses correct subject for runtime_critical', function () {
    Mail::fake();

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('runtime_critical', 'Runtime critical');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        return $mail->eventSubject === 'Runtime Critical';
    });
});

it('notify uses correct subject for ups_disconnected', function () {
    Mail::fake();

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('ups_disconnected', 'UPS disconnected');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        return $mail->eventSubject === 'UPS Disconnected';
    });
});

it('notify uses correct subject for ups_reconnected', function () {
    Mail::fake();

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('ups_reconnected', 'UPS reconnected');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        return $mail->eventSubject === 'UPS Reconnected';
    });
});

it('notify uses Alert subject for unknown event type', function () {
    Mail::fake();

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('something_unknown', 'Unknown event');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        return $mail->eventSubject === 'Alert';
    });
});

// --- getHostname() tests ---

it('uses hostname from settings', function () {
    Mail::fake();

    Setting::set('hostname', 'my-pipower');
    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('power_lost', 'Test');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        return $mail->hostname === 'my-pipower';
    });
});

it('falls back to gethostname when setting is empty', function () {
    Mail::fake();

    Recipient::create(['email' => 'user@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->notify('power_lost', 'Test');

    Mail::assertSent(\App\Mail\PowerEventMail::class, function ($mail) {
        // Should be system hostname, not empty
        return ! empty($mail->hostname);
    });
});

// --- sendSmsNotifications() edge cases ---

it('skips sms when plivo credentials are empty', function () {
    Http::fake();

    config([
        'pipower.plivo.auth_id' => '',
        'pipower.plivo.auth_token' => '',
        'pipower.plivo.from_number' => '',
    ]);

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $service = new NotificationService;
    $service->sendSmsNotifications('test-host', 'Test', 'Test message');

    Http::assertNothingSent();
});

it('skips sms when only auth_id is missing', function () {
    Http::fake();

    config([
        'pipower.plivo.auth_id' => '',
        'pipower.plivo.auth_token' => 'token',
        'pipower.plivo.from_number' => '+15550000000',
    ]);

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $service = new NotificationService;
    $service->sendSmsNotifications('test-host', 'Test', 'Test message');

    Http::assertNothingSent();
});

// --- sendTestNotification() tests ---

it('sendTestNotification returns email results', function () {
    Mail::fake();

    Recipient::create(['email' => 'test@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $results = $service->sendTestNotification();

    expect($results['email'])->toHaveCount(1)
        ->and($results['email'][0]['recipient'])->toBe('test@example.com')
        ->and($results['email'][0]['success'])->toBeTrue();
});

it('sendTestNotification returns empty arrays when no recipients', function () {
    $service = new NotificationService;
    $results = $service->sendTestNotification();

    expect($results['email'])->toBeEmpty()
        ->and($results['sms'])->toBeEmpty();
});

it('sendTestNotification captures email failure', function () {
    Mail::shouldReceive('to->send')->andThrow(new \Exception('SMTP connection failed'));

    Recipient::create(['email' => 'test@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $results = $service->sendTestNotification();

    expect($results['email'])->toHaveCount(1)
        ->and($results['email'][0]['success'])->toBeFalse()
        ->and($results['email'][0]['error'])->toContain('SMTP connection failed');
});

// --- Exception handling ---

it('logs error when email fails during notify', function () {
    Log::spy();
    Mail::shouldReceive('to->send')->andThrow(new \Exception('Connection timed out'));

    Recipient::create(['email' => 'test@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->sendEmailNotifications('power_lost', 'Power Lost', 'Test', 'test-host');

    Log::shouldHaveReceived('error')->with(Mockery::pattern('/Failed to send email/'));
});

it('logs error when sms fails during notify', function () {
    Log::spy();
    Http::fake([
        'api.plivo.com/*' => Http::response([], 500),
    ]);

    // To trigger the catch block, we need a real exception
    Http::shouldReceive('withBasicAuth->post')->andThrow(new \Exception('Network error'));

    config([
        'pipower.plivo.auth_id' => 'test_auth_id',
        'pipower.plivo.auth_token' => 'test_auth_token',
        'pipower.plivo.from_number' => '+15550000000',
    ]);

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $service = new NotificationService;
    $service->sendSmsNotifications('test-host', 'Test', 'Test message');

    Log::shouldHaveReceived('error')->with(Mockery::pattern('/Failed to send SMS/'));
});

it('sends to multiple email recipients', function () {
    Mail::fake();

    Recipient::create(['email' => 'first@example.com', 'enabled' => true]);
    Recipient::create(['email' => 'second@example.com', 'enabled' => true]);

    $service = new NotificationService;
    $service->sendEmailNotifications('power_lost', 'Power Lost', 'Test', 'test-host');

    Mail::assertSent(\App\Mail\PowerEventMail::class, 2);
});

it('sends to multiple phone recipients', function () {
    Http::fake([
        'api.plivo.com/*' => Http::response(['message' => 'sent'], 200),
    ]);

    config([
        'pipower.plivo.auth_id' => 'test_auth_id',
        'pipower.plivo.auth_token' => 'test_auth_token',
        'pipower.plivo.from_number' => '+15550000000',
    ]);

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);
    Recipient::create(['phone' => '+15559876543', 'enabled' => true]);

    $service = new NotificationService;
    $service->sendSmsNotifications('test-host', 'Power Lost', 'Test');

    Http::assertSentCount(2);
});

// --- SMS result reporting ---

function configurePlivo(): void
{
    config([
        'pipower.plivo.auth_id' => 'test_auth_id',
        'pipower.plivo.auth_token' => 'test_auth_token',
        'pipower.plivo.from_number' => '+15550000000',
    ]);
}

it('sendTestNotification reports sms success when plivo accepts the message', function () {
    Http::fake(['api.plivo.com/*' => Http::response(['message' => 'message(s) queued'], 202)]);
    configurePlivo();

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $results = (new NotificationService)->sendTestNotification();

    expect($results['sms'])->toBe([['recipient' => '+15551234567', 'success' => true]]);
});

it('sendTestNotification reports sms failure when plivo rejects the credentials', function () {
    Http::fake(['api.plivo.com/*' => Http::response('Could not verify your access level for that URL.', 401)]);
    configurePlivo();

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $results = (new NotificationService)->sendTestNotification();

    expect($results['sms'][0]['success'])->toBeFalse()
        ->and($results['sms'][0]['error'])->toContain('Auth ID or Auth Token');
});

it('sendTestNotification includes the plivo error message', function () {
    Http::fake(['api.plivo.com/*' => Http::response(['api_id' => 'x', 'error' => 'insufficient credit'], 400)]);
    configurePlivo();

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $results = (new NotificationService)->sendTestNotification();

    expect($results['sms'][0]['success'])->toBeFalse()
        ->and($results['sms'][0]['error'])->toBe('Plivo error (HTTP 400): insufficient credit');
});

it('sendTestNotification reports sms failure when plivo is not configured', function () {
    Http::fake();
    config(['pipower.plivo.auth_id' => '', 'pipower.plivo.auth_token' => '', 'pipower.plivo.from_number' => '']);

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    $results = (new NotificationService)->sendTestNotification();

    expect($results['sms'][0]['success'])->toBeFalse()
        ->and($results['sms'][0]['error'])->toContain('not configured');
    Http::assertNothingSent();
});

it('sendTestNotification sends exactly one sms per phone recipient', function () {
    Http::fake(['api.plivo.com/*' => Http::response([], 202)]);
    configurePlivo();

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);
    Recipient::create(['phone' => '+15559876543', 'enabled' => true]);
    Recipient::create(['phone' => '+15550001111', 'enabled' => true]);

    $results = (new NotificationService)->sendTestNotification();

    expect($results['sms'])->toHaveCount(3);
    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => $request['dst'] === '+15559876543');
});

it('logs an error when plivo rejects an sms during notify', function () {
    Log::spy();
    Http::fake(['api.plivo.com/*' => Http::response(['error' => 'invalid dst'], 400)]);
    configurePlivo();

    Recipient::create(['phone' => '+15551234567', 'enabled' => true]);

    (new NotificationService)->sendSmsNotifications('test-host', 'Test', 'Test message');

    Log::shouldHaveReceived('error')->with(Mockery::pattern('/Failed to send SMS to \+15551234567: Plivo error \(HTTP 400\): invalid dst/'));
});

it('notify sends both an email and an sms to a recipient with both', function () {
    Mail::fake();
    Http::fake([
        'api.plivo.com/*' => Http::response(['message' => 'sent'], 200),
    ]);

    config([
        'pipower.plivo.auth_id' => 'test_auth_id',
        'pipower.plivo.auth_token' => 'test_auth_token',
        'pipower.plivo.from_number' => '+15550000000',
    ]);

    Recipient::create(['email' => 'both@example.com', 'phone' => '+15551234567', 'enabled' => true]);

    (new NotificationService)->notify('power_lost', 'Running on battery');

    Mail::assertSent(\App\Mail\PowerEventMail::class, fn ($mail) => $mail->hasTo('both@example.com'));
    Http::assertSent(fn ($request) => $request['dst'] === '+15551234567');
    Http::assertSentCount(1);
});

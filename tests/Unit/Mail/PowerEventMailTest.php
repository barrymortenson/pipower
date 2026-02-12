<?php

use App\Mail\PowerEventMail;

it('subject includes hostname prefix', function () {
    $mail = new PowerEventMail('power_lost', 'Power Outage', 'Power outage detected', 'pipower-01');

    $mail->assertHasSubject('pipower-01: Power Outage');
});

it('uses correct from address and name', function () {
    config(['mail.from.address' => 'ups@example.com']);

    $mail = new PowerEventMail('power_lost', 'Power Outage', 'Power outage detected', 'pipower-01');

    $mail->assertFrom('ups@example.com', 'pipower-01');
});

it('passes correct data to markdown content', function () {
    $mail = new PowerEventMail('power_lost', 'Power Outage', 'Power outage detected', 'pipower-01');

    expect($mail->eventType)->toBe('power_lost')
        ->and($mail->eventSubject)->toBe('Power Outage')
        ->and($mail->message)->toBe('Power outage detected')
        ->and($mail->hostname)->toBe('pipower-01');
});

it('renders without errors for each event type', function (string $eventType, string $subject) {
    $mail = new PowerEventMail($eventType, $subject, 'Test message', 'pipower-01');

    $rendered = $mail->render();

    expect($rendered)->toBeString()
        ->and($rendered)->toContain($subject)
        ->and($rendered)->toContain('pipower-01');
})->with([
    'power_lost' => ['power_lost', 'Power Outage'],
    'power_restored' => ['power_restored', 'Power Restored'],
    'battery_low' => ['battery_low', 'Battery Critical'],
    'ups_disconnected' => ['ups_disconnected', 'UPS Disconnected'],
    'ups_reconnected' => ['ups_reconnected', 'UPS Reconnected'],
    'test' => ['test', 'Test Notification'],
]);

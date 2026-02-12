<?php

use App\Models\Recipient;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends test and shows results', function () {
    Recipient::create(['type' => 'email', 'value' => 'test@example.com', 'enabled' => true]);

    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('sendTestNotification')->once()->andReturn([
            'email' => [
                ['recipient' => 'test@example.com', 'success' => true],
            ],
            'sms' => [],
        ]);
    });

    $this->artisan('power:notify-test')
        ->expectsOutputToContain('test@example.com')
        ->assertExitCode(0);
});

it('shows error when no recipients', function () {
    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('sendTestNotification')->once()->andReturn([
            'email' => [],
            'sms' => [],
        ]);
    });

    $this->artisan('power:notify-test')
        ->expectsOutputToContain('No recipients configured')
        ->assertExitCode(1);
});

it('handles both email and sms results', function () {
    Recipient::create(['type' => 'email', 'value' => 'test@example.com', 'enabled' => true]);
    Recipient::create(['type' => 'phone', 'value' => '+15551234567', 'enabled' => true]);

    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('sendTestNotification')->once()->andReturn([
            'email' => [
                ['recipient' => 'test@example.com', 'success' => true],
            ],
            'sms' => [
                ['recipient' => '+15551234567', 'success' => true],
            ],
        ]);
    });

    // Exits 0 because there are results (rows are not empty)
    $this->artisan('power:notify-test')
        ->assertExitCode(0);
});

it('shows failure status for failed notifications', function () {
    Recipient::create(['type' => 'email', 'value' => 'bad@example.com', 'enabled' => true]);

    $this->mock(NotificationService::class, function ($mock) {
        $mock->shouldReceive('sendTestNotification')->once()->andReturn([
            'email' => [
                ['recipient' => 'bad@example.com', 'success' => false, 'error' => 'Connection refused'],
            ],
            'sms' => [],
        ]);
    });

    $this->artisan('power:notify-test')
        ->expectsOutputToContain('bad@example.com')
        ->assertExitCode(0);
});

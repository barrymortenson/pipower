<?php

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Pin .env defaults so the developer's local .env doesn't leak into tests
    config([
        'pipower.smtp.host' => 'smtp.env.test',
        'pipower.smtp.port' => 587,
        'pipower.smtp.username' => 'env-user',
        'pipower.smtp.password' => 'env-password',
        'pipower.smtp.from_address' => 'env@example.com',
        'pipower.smtp.from_name' => 'Env Name',
        'pipower.plivo.auth_id' => '',
        'pipower.plivo.auth_token' => '',
        'pipower.plivo.from_number' => '',
    ]);
});

it('returns .env defaults when nothing has been saved', function () {
    $this->getJson('/api/settings/notifications')
        ->assertOk()
        ->assertJson([
            'smtp_host' => 'smtp.env.test',
            'smtp_port' => 587,
            'smtp_username' => 'env-user',
            'smtp_password_set' => true,
            'smtp_from_address' => 'env@example.com',
            'plivo_auth_token_set' => false,
        ]);
});

it('never returns secrets', function () {
    $this->putJson('/api/settings/notifications', [
        'smtp_password' => 'super-secret',
        'plivo_auth_token' => 'plivo-secret',
    ])->assertOk();

    $response = $this->getJson('/api/settings/notifications')->assertOk();

    expect($response->getContent())
        ->not->toContain('super-secret')
        ->not->toContain('plivo-secret')
        ->not->toContain('env-password');
    $response->assertJsonMissingPath('smtp_password')
        ->assertJsonMissingPath('plivo_auth_token');
});

it('saves smtp settings', function () {
    $this->putJson('/api/settings/notifications', [
        'smtp_host' => 'smtp.ui.test',
        'smtp_port' => 465,
        'smtp_username' => 'ui-user',
        'smtp_password' => 'ui-password',
        'smtp_from_address' => 'ui@example.com',
        'smtp_from_name' => 'UI Name',
    ])
        ->assertOk()
        ->assertJson([
            'smtp_host' => 'smtp.ui.test',
            'smtp_port' => 465,
            'smtp_username' => 'ui-user',
            'smtp_password_set' => true,
            'smtp_from_address' => 'ui@example.com',
            'smtp_from_name' => 'UI Name',
        ]);
});

it('saves plivo settings', function () {
    $this->putJson('/api/settings/notifications', [
        'plivo_auth_id' => 'MAXXXXXXXX',
        'plivo_auth_token' => 'token123',
        'plivo_from_number' => '+15551234567',
    ])
        ->assertOk()
        ->assertJson([
            'plivo_auth_id' => 'MAXXXXXXXX',
            'plivo_auth_token_set' => true,
            'plivo_from_number' => '+15551234567',
        ]);
});

it('encrypts secrets at rest', function () {
    $this->putJson('/api/settings/notifications', [
        'smtp_password' => 'ui-password',
        'plivo_auth_token' => 'token123',
    ])->assertOk();

    expect(Setting::get('smtp_password'))->not->toBe('ui-password')
        ->and(Setting::get('plivo_auth_token'))->not->toBe('token123')
        ->and(decrypt(Setting::get('smtp_password'), false))->toBe('ui-password');
});

it('keeps the saved password when none is sent and the server is unchanged', function () {
    $this->putJson('/api/settings/notifications', ['smtp_password' => 'ui-password'])->assertOk();
    $this->putJson('/api/settings/notifications', [
        'smtp_host' => 'SMTP.env.test',
        'smtp_from_name' => 'New Name',
        'smtp_password' => null,
    ])->assertOk();

    expect(decrypt(Setting::get('smtp_password'), false))->toBe('ui-password');
});

it('drops the saved password when the server changes without a new password', function () {
    $this->putJson('/api/settings/notifications', ['smtp_password' => 'ui-password'])->assertOk();

    $this->putJson('/api/settings/notifications', ['smtp_host' => 'attacker.test'])
        ->assertOk()
        ->assertJson(['smtp_host' => 'attacker.test', 'smtp_password_set' => false]);
});

it('drops the .env password when the server changes without a new password', function () {
    $this->putJson('/api/settings/notifications', ['smtp_host' => 'attacker.test'])
        ->assertOk()
        ->assertJson(['smtp_password_set' => false]);
});

it('keeps a new password sent together with a server change', function () {
    $this->putJson('/api/settings/notifications', [
        'smtp_host' => 'smtp.new.test',
        'smtp_password' => 'new-password',
    ])
        ->assertOk()
        ->assertJson(['smtp_password_set' => true]);

    expect(decrypt(Setting::get('smtp_password'), false))->toBe('new-password');
});

it('clears the saved password and token on request', function () {
    $this->putJson('/api/settings/notifications', [
        'smtp_password' => 'ui-password',
        'plivo_auth_token' => 'token123',
    ])->assertOk();

    $this->putJson('/api/settings/notifications', [
        'clear_smtp_password' => true,
        'clear_plivo_auth_token' => true,
    ])
        ->assertOk()
        ->assertJson([
            'smtp_password_set' => false,
            'plivo_auth_token_set' => false,
        ]);
});

it('validates input', function () {
    $this->putJson('/api/settings/notifications', [
        'smtp_port' => 70000,
        'smtp_from_address' => 'not-an-email',
        'plivo_from_number' => 'abc',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['smtp_port', 'smtp_from_address', 'plivo_from_number']);
});

it('rejects a plivo from number that is not +1 and 10 digits', function (string $number) {
    $this->putJson('/api/settings/notifications', ['plivo_from_number' => $number])
        ->assertStatus(422)
        ->assertJsonValidationErrors('plivo_from_number');
})->with(['8052053000', '+1805205300', '+1 805 205 3000', '+448052053000', '+10052053000']);

it('accepts a plivo from number of +1 and 10 digits', function () {
    $this->putJson('/api/settings/notifications', ['plivo_from_number' => '+18052053000'])
        ->assertOk()
        ->assertJsonPath('plivo_from_number', '+18052053000');
});

it('clears a saved plivo from number', function () {
    $this->putJson('/api/settings/notifications', ['plivo_from_number' => '+18052053000'])->assertOk();

    $this->putJson('/api/settings/notifications', ['plivo_from_number' => null])
        ->assertOk()
        ->assertJsonPath('plivo_from_number', null);
});

<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * SMTP and Plivo delivery settings.
 *
 * Values saved from the web UI are stored in the settings table and take
 * precedence over the .env defaults in config/pipower.php. Secrets (SMTP
 * password, Plivo auth token) are encrypted with APP_KEY and are never
 * returned by toArray().
 */
class NotificationSettingsService
{
    /** Setting key => config/pipower.php fallback key */
    private const FIELDS = [
        'smtp_host' => 'pipower.smtp.host',
        'smtp_port' => 'pipower.smtp.port',
        'smtp_username' => 'pipower.smtp.username',
        'smtp_password' => 'pipower.smtp.password',
        'smtp_from_address' => 'pipower.smtp.from_address',
        'smtp_from_name' => 'pipower.smtp.from_name',
        'plivo_auth_id' => 'pipower.plivo.auth_id',
        'plivo_auth_token' => 'pipower.plivo.auth_token',
        'plivo_from_number' => 'pipower.plivo.from_number',
    ];

    private const SECRETS = ['smtp_password', 'plivo_auth_token'];

    /**
     * Effective settings for the UI. Secrets are reported only as set or not set.
     */
    public function toArray(): array
    {
        $values = $this->effective();

        return [
            'smtp_host' => $values['smtp_host'],
            'smtp_port' => $values['smtp_port'] !== null && $values['smtp_port'] !== '' ? (int) $values['smtp_port'] : null,
            'smtp_username' => $values['smtp_username'],
            'smtp_password_set' => filled($values['smtp_password']),
            'smtp_from_address' => $values['smtp_from_address'],
            'smtp_from_name' => $values['smtp_from_name'],
            'plivo_auth_id' => $values['plivo_auth_id'],
            'plivo_auth_token_set' => filled($values['plivo_auth_token']),
            'plivo_from_number' => $values['plivo_from_number'],
        ];
    }

    /**
     * Save settings from the UI.
     *
     * Secret fields are only changed when a new value is given, or when
     * clear_smtp_password / clear_plivo_auth_token is true.
     */
    public function update(array $data): void
    {
        foreach (array_keys(self::FIELDS) as $key) {
            if (in_array($key, self::SECRETS, true)) {
                if (! empty($data["clear_{$key}"])) {
                    Setting::set($key, null);
                } elseif (filled($data[$key] ?? null)) {
                    Setting::set($key, Crypt::encryptString((string) $data[$key]));
                }

                continue;
            }

            if (array_key_exists($key, $data)) {
                Setting::set($key, $data[$key]);
            }
        }
    }

    /**
     * Plivo credentials in effect (UI values override .env).
     *
     * @return array{auth_id: ?string, auth_token: ?string, from_number: ?string}
     */
    public function plivo(): array
    {
        $values = $this->effective();

        return [
            'auth_id' => $values['plivo_auth_id'],
            'auth_token' => $values['plivo_auth_token'],
            'from_number' => $values['plivo_from_number'],
        ];
    }

    /**
     * Push SMTP values saved in the UI into Laravel's mail config.
     *
     * Only keys saved from the UI are overridden, so a device configured
     * purely through .env behaves exactly as before.
     */
    public function applyMailConfig(): void
    {
        $stored = $this->stored();

        $map = [
            'smtp_host' => 'mail.mailers.smtp.host',
            'smtp_port' => 'mail.mailers.smtp.port',
            'smtp_username' => 'mail.mailers.smtp.username',
            'smtp_password' => 'mail.mailers.smtp.password',
            'smtp_from_address' => 'mail.from.address',
            'smtp_from_name' => 'mail.from.name',
        ];

        $overrides = [];
        foreach ($map as $key => $configKey) {
            if (array_key_exists($key, $stored)) {
                $overrides[$configKey] = $stored[$key];
            }
        }

        if ($overrides === []) {
            return;
        }

        // An SMTP server entered in the UI means "send over SMTP", even if
        // .env selects another mailer (e.g. log).
        if (filled($stored['smtp_host'] ?? null)) {
            $overrides['mail.default'] = 'smtp';
        }

        config($overrides);

        // Drop cached mailer instances so the new config is used.
        app('mail.manager')->forgetMailers();
    }

    /**
     * Every field, using the UI value when one was saved, else the .env default.
     */
    private function effective(): array
    {
        $stored = $this->stored();
        $values = [];

        foreach (self::FIELDS as $key => $configKey) {
            $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : config($configKey);
        }

        return $values;
    }

    /**
     * Values saved from the UI, with secrets decrypted. Keys absent from
     * the result were never saved and fall back to .env.
     */
    private function stored(): array
    {
        $rows = Setting::whereIn('key', array_keys(self::FIELDS))->get();
        $stored = [];

        foreach ($rows as $row) {
            $key = $row->key;
            $value = is_array($row->value) ? ($row->value['value'] ?? null) : $row->value;

            if (in_array($key, self::SECRETS, true) && filled($value)) {
                $value = $this->decrypt($key, $value);
            }

            $stored[$key] = $value;
        }

        return $stored;
    }

    private function decrypt(string $key, string $value): ?string
    {
        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            Log::warning("Could not decrypt saved {$key} (APP_KEY may have changed). Re-enter it in Notifications settings.");

            return null;
        }
    }
}

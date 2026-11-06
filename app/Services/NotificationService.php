<?php

namespace App\Services;

use App\Mail\PowerEventMail;
use App\Models\Recipient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    private NotificationSettingsService $settings;

    public function __construct(?NotificationSettingsService $settings = null)
    {
        $this->settings = $settings ?? new NotificationSettingsService;
    }

    public function notify(string $eventType, string $message): void
    {
        $subject = $this->getSubjectForEvent($eventType);
        $hostname = $this->getHostname();

        $this->sendEmailNotifications($eventType, $subject, $message, $hostname);
        $this->sendSmsNotifications($hostname, $subject, $message);
    }

    public function sendEmailNotifications(string $eventType, string $subject, string $message, string $hostname): void
    {
        $recipients = Recipient::enabled()->emails()->get();

        $this->settings->applyMailConfig();

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->value)->send(new PowerEventMail($eventType, $subject, $message, $hostname));
            } catch (\Exception $e) {
                Log::error("Failed to send email to {$recipient->value}: {$e->getMessage()}");
            }
        }
    }

    public function sendSmsNotifications(string $hostname, string $subject, string $message): void
    {
        $recipients = Recipient::enabled()->phones()->get();

        ['auth_id' => $authId, 'auth_token' => $authToken, 'from_number' => $fromNumber] = $this->settings->plivo();

        if (empty($authId) || empty($authToken) || empty($fromNumber)) {
            Log::warning('Plivo credentials not configured, skipping SMS');

            return;
        }

        $smsText = "{$hostname}: {$subject} - {$message}";

        foreach ($recipients as $recipient) {
            try {
                Http::withBasicAuth($authId, $authToken)
                    ->post("https://api.plivo.com/v1/Account/{$authId}/Message/", [
                        'src' => $fromNumber,
                        'dst' => $recipient->value,
                        'text' => $smsText,
                    ]);
            } catch (\Exception $e) {
                Log::error("Failed to send SMS to {$recipient->value}: {$e->getMessage()}");
            }
        }
    }

    public function sendTestNotification(): array
    {
        $results = ['email' => [], 'sms' => []];
        $hostname = $this->getHostname();

        $emailRecipients = Recipient::enabled()->emails()->get();
        $this->settings->applyMailConfig();
        foreach ($emailRecipients as $recipient) {
            try {
                Mail::to($recipient->value)->send(new PowerEventMail(
                    'test',
                    'Test Notification',
                    'This is a test notification to verify your alert settings are working correctly.',
                    $hostname
                ));
                $results['email'][] = ['recipient' => $recipient->value, 'success' => true];
            } catch (\Exception $e) {
                $results['email'][] = ['recipient' => $recipient->value, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        $phoneRecipients = Recipient::enabled()->phones()->get();
        foreach ($phoneRecipients as $recipient) {
            try {
                $this->sendSmsNotifications($hostname, 'Test', 'This is a test notification.');
                $results['sms'][] = ['recipient' => $recipient->value, 'success' => true];
            } catch (\Exception $e) {
                $results['sms'][] = ['recipient' => $recipient->value, 'success' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    private function getSubjectForEvent(string $eventType): string
    {
        return match ($eventType) {
            'power_lost' => 'Power Outage',
            'power_restored' => 'Power Restored',
            'battery_low' => 'Battery Critical',
            'runtime_critical' => 'Runtime Critical',
            'ups_disconnected' => 'UPS Disconnected',
            'ups_reconnected' => 'UPS Reconnected',
            default => 'Alert',
        };
    }

    private function getHostname(): string
    {
        // Use configured hostname from settings, fall back to system hostname
        return \App\Models\Setting::get('hostname') ?: gethostname() ?: 'UPS Monitor';
    }
}

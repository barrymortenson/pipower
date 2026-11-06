<?php

namespace Database\Seeders;

use App\Models\Recipient;
use App\Rules\UsPhoneNumber;
use Illuminate\Database\Seeder;

class DefaultRecipientsSeeder extends Seeder
{
    /**
     * Seed default notification recipients from .env configuration.
     *
     * Format: "Name <value>, Name <value>"
     * Type is auto-detected: @ = email, anything else = phone. Phone
     * numbers must be US numbers; others are skipped with a warning.
     * Entries that share a name become one recipient with both an email
     * address and a phone number, e.g. "Barry <b@example.com>, Barry <+15551234567>".
     */
    public function run(): void
    {
        $raw = config('pipower.defaults.recipients');

        if (empty($raw)) {
            return;
        }

        foreach ($this->group($this->parse($raw)) as $contact) {
            $exists = Recipient::query()
                ->where(function ($query) use ($contact) {
                    if ($contact['email'] !== null) {
                        $query->orWhere('email', $contact['email']);
                    }
                    if ($contact['phone'] !== null) {
                        $query->orWhere('phone', $contact['phone']);
                    }
                })
                ->exists();

            if (! $exists) {
                Recipient::create([...$contact, 'enabled' => true]);
            }
        }
    }

    /**
     * Combine parsed entries into recipients. A named entry merges into an
     * earlier one with the same name when that one has no value of its type
     * yet. Unnamed entries always stand alone.
     *
     * @param  array<int, array{name: ?string, type: string, value: string}>  $contacts
     * @return array<int, array{name: ?string, email: ?string, phone: ?string}>
     */
    private function group(array $contacts): array
    {
        $recipients = [];

        foreach ($contacts as $contact) {
            $field = $contact['type'];
            $match = null;

            if ($contact['name'] !== null) {
                foreach ($recipients as $index => $recipient) {
                    if ($recipient['name'] === $contact['name'] && $recipient[$field] === null) {
                        $match = $index;
                        break;
                    }
                }
            }

            if ($match === null) {
                $recipients[] = ['name' => $contact['name'], 'email' => null, 'phone' => null];
                $match = array_key_last($recipients);
            }

            $recipients[$match][$field] = $contact['value'];
        }

        return $recipients;
    }

    /**
     * Parse "Name <value>, Name <value>" into structured contacts.
     */
    private function parse(string $raw): array
    {
        $contacts = [];

        // Split on comma, but respect angle brackets
        foreach (preg_split('/,\s*(?=[^>]*(?:<|$))/', $raw) as $entry) {
            $entry = trim($entry);

            if (empty($entry)) {
                continue;
            }

            // Match "Name <value>" format
            if (preg_match('/^(.+?)\s*<(.+?)>\s*$/', $entry, $matches)) {
                $name = trim($matches[1]);
                $value = trim($matches[2]);
            } else {
                // Bare value with no name
                $name = null;
                $value = trim($entry);
            }

            $type = str_contains($value, '@') ? 'email' : 'phone';

            if ($type === 'phone') {
                // Store US numbers as +1XXXXXXXXXX. Anything else would fail
                // validation the first time the recipient is edited, so skip it.
                $number = UsPhoneNumber::normalize($value);

                if ($number === null) {
                    $this->command?->warn("Skipped default recipient \"{$entry}\": not a valid US phone number.");

                    continue;
                }

                $value = $number;
            }

            $contacts[] = [
                'name' => $name,
                'type' => $type,
                'value' => $value,
            ];
        }

        return $contacts;
    }
}

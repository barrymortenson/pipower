<?php

namespace Database\Seeders;

use App\Models\Recipient;
use Illuminate\Database\Seeder;

class DefaultRecipientsSeeder extends Seeder
{
    /**
     * Seed default notification recipients from .env configuration.
     *
     * Format: "Name <value>, Name <value>"
     * Type is auto-detected: @ = email, + = phone
     */
    public function run(): void
    {
        $raw = config('pipower.defaults.recipients');

        if (empty($raw)) {
            return;
        }

        foreach ($this->parse($raw) as $contact) {
            Recipient::firstOrCreate(
                ['type' => $contact['type'], 'value' => $contact['value']],
                ['name' => $contact['name'], 'enabled' => true]
            );
        }
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

            $contacts[] = [
                'name' => $name,
                'type' => $type,
                'value' => $value,
            ];
        }

        return $contacts;
    }
}

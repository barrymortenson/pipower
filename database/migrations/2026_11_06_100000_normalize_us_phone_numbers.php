<?php

use App\Rules\UsPhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phone numbers are now stored as +1 followed by 10 digits. Rewrite
 * recipient phones and the saved Plivo from number into that form.
 * Numbers that are not valid US numbers are left as they are and logged,
 * so they can be fixed by hand in the Notifications page.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('recipients')->whereNotNull('phone')->get(['id', 'phone']) as $row) {
            $number = UsPhoneNumber::normalize($row->phone);

            if ($number === null) {
                Log::warning("Recipient {$row->id} phone \"{$row->phone}\" is not a valid US number; left unchanged.");
            } elseif ($number !== $row->phone) {
                DB::table('recipients')->where('id', $row->id)->update(['phone' => $number]);
            }
        }

        $setting = DB::table('settings')->where('key', 'plivo_from_number')->first();
        $from = $setting ? (json_decode($setting->value, true)['value'] ?? null) : null;

        if (is_string($from) && $from !== '') {
            $number = UsPhoneNumber::normalize($from);

            if ($number === null) {
                Log::warning("Plivo from number \"{$from}\" is not a valid US number; left unchanged.");
            } elseif ($number !== $from) {
                DB::table('settings')->where('key', 'plivo_from_number')
                    ->update(['value' => json_encode(['value' => $number])]);
            }
        }
    }

    /**
     * The original formatting is not kept, so there is nothing to restore.
     */
    public function down(): void
    {
        //
    }
};

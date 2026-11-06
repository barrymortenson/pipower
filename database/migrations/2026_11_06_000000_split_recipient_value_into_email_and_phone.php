<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A recipient used to hold one channel (type + value). It now holds an
 * optional email address and an optional phone number, so one person can
 * get both email and SMS alerts from a single record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->string('email')->nullable()->after('name');
            $table->string('phone')->nullable()->after('email');
        });

        DB::table('recipients')->where('type', 'email')->update(['email' => DB::raw('value')]);
        DB::table('recipients')->where('type', 'phone')->update(['phone' => DB::raw('value')]);

        Schema::table('recipients', function (Blueprint $table) {
            $table->dropColumn(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::table('recipients', function (Blueprint $table) {
            $table->string('type')->default('email')->after('name');
            $table->string('value')->default('')->after('type');
        });

        // A record with both channels becomes two single-channel records.
        foreach (DB::table('recipients')->get() as $row) {
            if ($row->email !== null) {
                DB::table('recipients')->where('id', $row->id)->update(['type' => 'email', 'value' => $row->email]);
            }

            if ($row->phone !== null) {
                if ($row->email === null) {
                    DB::table('recipients')->where('id', $row->id)->update(['type' => 'phone', 'value' => $row->phone]);
                } else {
                    DB::table('recipients')->insert([
                        'name' => $row->name,
                        'type' => 'phone',
                        'value' => $row->phone,
                        'enabled' => $row->enabled,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                    ]);
                }
            }
        }

        Schema::table('recipients', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone']);
        });
    }
};

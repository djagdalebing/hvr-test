<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ensure the navbar bell icon renders by enabling the
 * notifications.integrated setting. Vebto leaves it unset; without it
 * `config.get('notifications.integrated')` returns null and the bell
 * widget never appears in <material-navbar>.
 */
class EnableIntegratedNotifications extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('settings')) return;
        // upsert via raw SQL — different Vebto versions have different
        // column shapes (name|key + value|setting) — be defensive.
        $exists = DB::table('settings')->where('name', 'notifications.integrated')->exists();
        if (!$exists) {
            DB::table('settings')->insert([
                'name'    => 'notifications.integrated',
                'value'   => '1',
                'private' => 0,
            ]);
        } else {
            DB::table('settings')->where('name', 'notifications.integrated')->update(['value' => '1']);
        }
        try { Cache::forget('settings.public'); } catch (\Throwable $e) {}
    }

    public function down()
    {
        if (!Schema::hasTable('settings')) return;
        try {
            DB::table('settings')->where('name', 'notifications.integrated')->update(['value' => '0']);
            Cache::forget('settings.public');
        } catch (\Throwable $e) {}
    }
}

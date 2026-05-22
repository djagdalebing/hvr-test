<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The earlier migration set the value as '1', but Vebto's Setting model
 * unwraps that with `ctype_digit($value) → (int) $value`, which Angular's
 * boolean coercion may misread depending on the value's source path
 * (raw DB read vs Setting model attribute accessor). The model also
 * has explicit handling for the literal string 'true' → bool(true), so
 * write that and we're guaranteed truthy on every read path. Also
 * invalidate every cache key Vebto uses for settings.
 */
class ForceEnableNotificationsIntegrated extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('settings')) return;

        DB::table('settings')->updateOrInsert(
            ['name' => 'notifications.integrated'],
            ['name' => 'notifications.integrated', 'value' => 'true', 'private' => 0]
        );

        // Vebto caches the de-private filtered settings under 'settings.public'.
        // The legacy filename varies between Vebto versions — clear all.
        foreach (['settings.public', 'settings', 'settings.all'] as $key) {
            try { Cache::forget($key); } catch (\Throwable $e) {}
        }
    }

    public function down()
    {
        if (!Schema::hasTable('settings')) return;
        DB::table('settings')
            ->where('name', 'notifications.integrated')
            ->update(['value' => 'false']);
        try { Cache::forget('settings.public'); } catch (\Throwable $e) {}
    }
}

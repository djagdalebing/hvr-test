<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ForceVideoPlayerSettings extends Migration
{
    /**
     * Earlier migrations only seeded streaming.show_header_play and
     * streaming.prefer_full when the rows didn't exist, to avoid clobbering
     * an admin's deliberate choice. On this site both rows had already been
     * seeded with the literal string 'false' (Setting.php's accessor casts
     * that to boolean false on read), so the inserts were skipped and the
     * play button stayed hidden.
     *
     * Force both to 'true'. The header play button + primary-video selector
     * are required for any creator-uploaded title to be playable; leaving
     * them off makes the upload flow effectively useless. If a future admin
     * wants to disable either, they can do so from the Settings UI.
     *
     * Also blow away the settings cache so the new values take effect on
     * the next request without needing a manual `cache:clear`.
     */
    public function up(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'streaming.show_header_play'],
            ['value' => 'true']
        );

        DB::table('settings')->updateOrInsert(
            ['name' => 'streaming.prefer_full'],
            ['value' => 'true']
        );

        Cache::forget('settings.public');
    }

    public function down(): void
    {
        // No-op: do not silently flip these back to 'false' on rollback —
        // it would re-break the player.
    }
}

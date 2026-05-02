<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class EnableShowHeaderPlay extends Migration
{
    /**
     * The Angular title-page header renders the play button only when:
     *
     *   settings.get("streaming.show_header_play") && primaryVideo
     *
     * This site's default-settings.php had a typo — it seeded the value at
     * "streaming.streaming.show_header_play" (double "streaming."), so the key
     * the bundle actually reads ("streaming.show_header_play") was never set.
     * The header play button has therefore never rendered for any title.
     *
     * Two-part cleanup:
     *   - Delete the typoed key so it stops shadowing the real one in the
     *     settings cache.
     *   - Insert "streaming.show_header_play" = '1' if it doesn't already
     *     exist (don't overwrite an admin's deliberate choice).
     */
    public function up(): void
    {
        DB::table('settings')->where('name', 'streaming.streaming.show_header_play')->delete();

        $exists = DB::table('settings')->where('name', 'streaming.show_header_play')->exists();
        if (!$exists) {
            DB::table('settings')->insert([
                'name'  => 'streaming.show_header_play',
                'value' => '1',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('name', 'streaming.show_header_play')->delete();
    }
}

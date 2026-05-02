<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class EnablePreferFullVideo extends Migration
{
    /**
     * The Angular player picks the "primary" header video using this logic:
     *
     *   prefer_full ? find(category='full' && type!='external')
     *               : find(category!='full' && type!='external')
     *
     * Default behavior (prefer_full unset) wants a trailer/clip as the header
     * preview. Creator-uploaded videos are saved as category='full', so when
     * a title only has a full upload (no trailer), the player finds nothing
     * and renders a gray placeholder instead of the play button.
     *
     * Flip the default to prefer_full=true so the header picks the full
     * upload. Only seeds the value if not already set, so a site admin who
     * has explicitly chosen the other behavior is never overridden.
     */
    public function up(): void
    {
        $exists = DB::table('settings')->where('name', 'streaming.prefer_full')->exists();
        if (!$exists) {
            DB::table('settings')->insert([
                'name'  => 'streaming.prefer_full',
                'value' => '1',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('name', 'streaming.prefer_full')->delete();
    }
}

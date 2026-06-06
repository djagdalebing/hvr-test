<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class NormalizeCreatorPosterPaths extends Migration
{
    /**
     * Earlier creator uploads stored poster/backdrop as '/storage/...'
     * (with a leading slash). Vebto's SPA prepends a base URL, so values
     * with a leading slash render as 'host.com//storage/...' and Hostinger's
     * edge 422s the doubled path. Strip the leading slash so the SPA can
     * concatenate cleanly. Only touches values that start with '/storage/'.
     */
    public function up()
    {
        DB::table('titles')
            ->where('poster', 'like', '/storage/%')
            ->update(['poster' => DB::raw("SUBSTRING(poster, 2)")]);

        DB::table('titles')
            ->where('backdrop', 'like', '/storage/%')
            ->update(['backdrop' => DB::raw("SUBSTRING(backdrop, 2)")]);
    }

    public function down()
    {
        // No-op: re-adding the leading slash would reintroduce the bug.
    }
}

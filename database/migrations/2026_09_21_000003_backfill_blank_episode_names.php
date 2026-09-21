<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give blank episodes a name.
 *
 * The repair in 2026_09_21_000002 only named episodes it created itself; rows
 * that already existed from the failed writes kept an empty name and rendered
 * as a blank row in the creator's episode list. Name them after their
 * position, which is what the UI would have defaulted to anyway.
 */
class BackfillBlankEpisodeNames extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('episodes') || !Schema::hasColumn('episodes', 'name')) {
            return;
        }

        DB::table('episodes')
            ->where(function ($q) {
                $q->whereNull('name')->orWhere('name', '');
            })
            ->orderBy('id')
            ->chunk(200, function ($episodes) {
                foreach ($episodes as $episode) {
                    DB::table('episodes')
                        ->where('id', $episode->id)
                        ->update([
                            'name' => 'Episode ' . (int) $episode->episode_number,
                        ]);
                }
            });
    }

    public function down()
    {
        // Nothing to undo.
    }
}

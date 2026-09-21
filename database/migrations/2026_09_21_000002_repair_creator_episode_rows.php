<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair series episodes created before the column names were fixed.
 *
 * The creator episode code was written against the original 2013 episodes
 * migration, which had `title` and `plot`. Those were renamed to `name` and
 * `description` in 2018_10_13_125603_update_episodes_table_to_v2, so every
 * Episode::create() failed. Two kinds of damage were left behind:
 *
 *  1. Videos tagged with season_num/episode_num but no episodes row, so the
 *     series had episode videos that no episode pointed at.
 *  2. Video names stringified from a Title model -- Episode has a title()
 *     belongsTo, so $episode->title returned the parent Title and Laravel's
 *     Model::__toString() serialised it into the name column as JSON.
 *
 * Idempotent: re-running changes nothing once the data is clean.
 */
class RepairCreatorEpisodeRows extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('videos') || !Schema::hasTable('episodes')) {
            return;
        }

        // 1. Give every orphaned episode video a real episodes row.
        $orphans = DB::table('videos')
            ->whereNotNull('user_id')
            ->whereNotNull('episode_num')
            ->get(['id', 'title_id', 'season_num', 'episode_num', 'episode_id']);

        foreach ($orphans as $video) {
            $seasonNumber = (int) ($video->season_num ?: 1);
            $episodeNumber = (int) $video->episode_num;

            $episode = DB::table('episodes')
                ->where('title_id', $video->title_id)
                ->where('season_number', $seasonNumber)
                ->where('episode_number', $episodeNumber)
                ->first(['id', 'name']);

            if (!$episode) {
                $seasonId = DB::table('seasons')
                    ->where('title_id', $video->title_id)
                    ->where('number', $seasonNumber)
                    ->value('id');

                if (!$seasonId) {
                    $seasonId = DB::table('seasons')->insertGetId([
                        'title_id' => $video->title_id,
                        'number' => $seasonNumber,
                        'episode_count' => 0,
                        'allow_update' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $episodeId = DB::table('episodes')->insertGetId([
                    'title_id' => $video->title_id,
                    'season_id' => $seasonId,
                    'season_number' => $seasonNumber,
                    'episode_number' => $episodeNumber,
                    'name' => "Episode {$episodeNumber}",
                    'allow_update' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $episode = (object) [
                    'id' => $episodeId,
                    'name' => "Episode {$episodeNumber}",
                ];
            }

            $update = [];
            if ((int) $video->episode_id !== (int) $episode->id) {
                $update['episode_id'] = $episode->id;
            }
            if (!empty($update)) {
                DB::table('videos')->where('id', $video->id)->update($update);
            }
        }

        // 2. Replace names that are a serialised Title with the episode's own.
        DB::table('videos')
            ->where('name', 'like', '{"id":%')
            ->whereNotNull('episode_num')
            ->orderBy('id')
            ->chunk(100, function ($videos) {
                foreach ($videos as $video) {
                    $name = DB::table('episodes')
                        ->where('id', $video->episode_id)
                        ->value('name');

                    DB::table('videos')
                        ->where('id', $video->id)
                        ->update([
                            'name' => $name ?: "Episode {$video->episode_num}",
                        ]);
                }
            });

        // 3. Bring the denormalised counters back in step.
        foreach (
            DB::table('episodes')->distinct()->pluck('title_id')
            as $titleId
        ) {
            $episodeCount = DB::table('episodes')
                ->where('title_id', $titleId)
                ->count();
            $seasonCount = DB::table('seasons')
                ->where('title_id', $titleId)
                ->count();

            DB::table('titles')
                ->where('id', $titleId)
                ->update([
                    'episode_count' => $episodeCount,
                    'season_count' => $seasonCount,
                ]);

            foreach (
                DB::table('seasons')->where('title_id', $titleId)->get(['id', 'number'])
                as $season
            ) {
                DB::table('seasons')
                    ->where('id', $season->id)
                    ->update([
                        'episode_count' => DB::table('episodes')
                            ->where('title_id', $titleId)
                            ->where('season_number', $season->number)
                            ->count(),
                    ]);
            }
        }
    }

    public function down()
    {
        // Nothing to undo -- this only repairs data that was already broken.
    }
}

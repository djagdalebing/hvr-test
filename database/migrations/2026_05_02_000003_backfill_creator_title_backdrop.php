<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class BackfillCreatorTitleBackdrop extends Migration
{
    /**
     * The Angular title-page header builds its image from this fallback chain:
     *
     *   primaryVideo.thumbnail || activeEpisode.poster
     *     || title.images[last] || title.backdrop
     *
     * It deliberately skips title.poster, so a creator title that only has a
     * poster (no backdrop, no images, no video thumbnail) renders as the gray
     * "broken image" placeholder even when the play logic has selected a
     * primary video. Mirror the poster into backdrop on every title that has
     * a creator upload but no backdrop set.
     */
    public function up(): void
    {
        $rows = DB::table('titles')
            ->select('titles.id', 'titles.poster')
            ->join('videos', 'videos.title_id', '=', 'titles.id')
            ->whereNotNull('videos.user_id')
            ->whereNotNull('titles.poster')
            ->where(function ($q) {
                $q->whereNull('titles.backdrop')->orWhere('titles.backdrop', '');
            })
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            DB::table('titles')->where('id', $row->id)->update([
                'backdrop' => $row->poster,
            ]);
        }
    }

    public function down(): void
    {
        // No-op: we cannot tell after the fact which backdrops were filled by
        // this migration vs set by the user.
    }
}

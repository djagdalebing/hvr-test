<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-video moderation state, for episodes.
 *
 * A title carries status + rejection_reason, which works while a title is one
 * video. A series is not: episode 6 can be awaiting review while episodes 1-5
 * are live, so the decision has to be recordable on the video.
 *
 * videos.approved already covered approved-vs-not, but not "reviewed and
 * turned down" -- without that, a rejected episode is indistinguishable from
 * one still queued and would sit in the moderation list forever.
 */
class AddEpisodeModerationToVideos extends Migration
{
    public function up()
    {
        Schema::table('videos', function (Blueprint $table) {
            if (!Schema::hasColumn('videos', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->index();
            }
            if (!Schema::hasColumn('videos', 'rejection_reason')) {
                $table->string('rejection_reason', 1000)->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('videos', function (Blueprint $table) {
            if (Schema::hasColumn('videos', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('videos', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
        });
    }
}

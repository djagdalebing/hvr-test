<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One-time reconciliation for content that was auto-approved BEFORE creator
 * submissions were changed to always require review.
 *
 * Trusted creators used to have their title auto-approved (status='approved')
 * while the video's own `approved` flag is gated separately — so such content
 * went live without review, and often as an "approved title / unapproved video"
 * mismatch (the public page then shows no playable video).
 *
 * Revert to 'pending' any title that was auto-approved but never actually
 * reviewed: it has creator-uploaded videos (user_id set) yet NONE of them are
 * approved. That's the signature of title-level auto-approval with no video
 * approval. Titles an admin genuinely approved flip their videos to approved,
 * so they have at least one approved creator video and are left untouched.
 * TMDB imports (videos have no user_id; titles often have NULL status) are also
 * untouched.
 */
class ReconcileCreatorContentApproval extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('titles') || !Schema::hasTable('videos')) {
            return;
        }

        $ids = DB::table('titles')
            ->where('titles.status', 'approved')
            // has at least one creator-uploaded video
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))->from('videos')
                    ->whereColumn('videos.title_id', 'titles.id')
                    ->whereNotNull('videos.user_id');
            })
            // …but none of its creator videos are approved
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('videos')
                    ->whereColumn('videos.title_id', 'titles.id')
                    ->whereNotNull('videos.user_id')
                    ->where('videos.approved', 1);
            })
            ->pluck('titles.id');

        if ($ids->isNotEmpty()) {
            DB::table('titles')->whereIn('id', $ids)->update(['status' => 'pending']);
        }

        Log::info('ReconcileCreatorContentApproval: reverted ' . $ids->count()
            . ' auto-approved-but-unreviewed title(s) to pending.');
    }

    public function down()
    {
        // No-op: reverted rows are indistinguishable from other pending content
        // afterwards, so this is intentionally not reversible.
    }
}

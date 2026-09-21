<?php

namespace App\Listeners;

use App\Title;
use App\Video;
use Common\Auth\Events\UsersDeleted;
use Illuminate\Support\Facades\DB;

/**
 * Take a deleted creator's titles off the site.
 *
 * Deleting a user removed their account, lists, notifications and
 * subscriptions but left their uploads completely untouched: videos.user_id
 * pointed at a row that no longer existed, while the titles kept
 * status = 'approved' and so stayed public. They even remained in the
 * "Exclusive Content Release" row, which only checks that user_id is NOT
 * NULL -- an orphaned id still satisfies that.
 *
 * The result was films live on the homepage, attributed to nobody, linking
 * to a creator page that 404s.
 *
 * Rather than delete the content outright -- which would destroy work an
 * admin may still need, and is unrecoverable -- mark it rejected. That
 * reuses the existing moderation machinery: the global scope hides it, the
 * homepage rows exclude it, and it shows up under Moderation > Rejected
 * where an admin can see what happened and Restore it if the deletion was a
 * mistake. The media files are deliberately NOT removed here; a takedown is
 * not a delete.
 */
class TakeDownDeletedCreatorContent
{
    const REASON = 'Creator account was deleted.';

    public function handle(UsersDeleted $event): void
    {
        $userIds = $event->users->pluck('id')->filter()->all();
        if (empty($userIds)) {
            return;
        }

        try {
            $titleIds = Video::whereIn('user_id', $userIds)
                ->whereNotNull('title_id')
                ->pluck('title_id')
                ->unique()
                ->values()
                ->all();

            if (!empty($titleIds)) {
                Title::withoutGlobalScope('approved')
                    ->whereIn('id', $titleIds)
                    ->update([
                        'status' => 'rejected',
                        'rejection_reason' => self::REASON,
                    ]);

                // Keep the videos unapproved so the player will not pick them
                // up and /admin/videos reflects the takedown.
                Video::whereIn('title_id', $titleIds)
                    ->whereNotNull('user_id')
                    ->update(['approved' => 0]);
            }

            // The public creator profile and portfolio are meaningless without
            // the account. The directory already filters on whereHas('user'),
            // so these are invisible either way -- this just stops orphaned
            // rows accumulating.
            if (DB::getSchemaBuilder()->hasTable('creator_profiles')) {
                DB::table('creator_profiles')
                    ->whereIn('user_id', $userIds)
                    ->delete();
            }
            if (DB::getSchemaBuilder()->hasTable('creator_projects')) {
                DB::table('creator_projects')
                    ->whereIn('user_id', $userIds)
                    ->delete();
            }
        } catch (\Throwable $e) {
            // Never let cleanup failure abort the deletion itself -- the user
            // row is already gone by the time this event fires.
            \Log::warning(
                'TakeDownDeletedCreatorContent failed: ' . $e->getMessage(),
            );
        }
    }
}

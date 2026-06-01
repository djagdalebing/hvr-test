<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill: any creator video whose title is pending or rejected should
 * have approved=0, so /admin/videos and the public player both reflect
 * the moderation state. Old uploads from before Phase 1 had approved=1
 * even when the title was pending.
 */
class SyncVideoApprovedWithTitleStatus extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('videos') || !Schema::hasTable('titles')) return;
        if (!Schema::hasColumn('titles', 'status')) return;

        // Pending / rejected → not approved
        DB::statement('
            UPDATE videos v
            JOIN titles t ON t.id = v.title_id
            SET v.approved = 0
            WHERE v.user_id IS NOT NULL
              AND t.status IN ("pending", "rejected")
        ');

        // Approved → approved
        DB::statement('
            UPDATE videos v
            JOIN titles t ON t.id = v.title_id
            SET v.approved = 1
            WHERE v.user_id IS NOT NULL
              AND t.status = "approved"
        ');
    }

    public function down()
    {
        // no-op — sync state cannot be safely reversed without knowing
        // the original approved flag per row.
    }
}

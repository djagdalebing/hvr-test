<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHEN a title was approved, so the homepage "Exclusive Content Release"
 * row can order creator titles by approval date (newest approved first) rather
 * than by creation/edit date. Backfills existing approved titles with their
 * updated_at as a best-effort proxy.
 */
class AddApprovedAtToTitles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('titles') || Schema::hasColumn('titles', 'approved_at')) {
            return;
        }

        Schema::table('titles', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->index()->after('status');
        });

        // Best-effort backfill: existing approved titles get their last-updated
        // time as the approval time (we have no better signal historically).
        DB::table('titles')
            ->where('status', 'approved')
            ->whereNull('approved_at')
            ->update(['approved_at' => DB::raw('updated_at')]);
    }

    public function down()
    {
        if (Schema::hasColumn('titles', 'approved_at')) {
            Schema::table('titles', function (Blueprint $table) {
                $table->dropColumn('approved_at');
            });
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddModerationToPeople extends Migration
{
    /**
     * People created by creators go through moderation (pending → approved),
     * mirroring the title queue. Existing TMDB-synced people default to
     * 'approved' so nothing currently public disappears. created_by tracks
     * which user added a person so the global scope can let that creator
     * preview their own pending person before an admin approves it.
     */
    public function up()
    {
        Schema::table('people', function (Blueprint $table) {
            if (!Schema::hasColumn('people', 'status')) {
                $table->string('status', 20)->default('approved')->index()->after('name');
            }
            if (!Schema::hasColumn('people', 'created_by')) {
                $table->unsignedInteger('created_by')->nullable()->index()->after('status');
            }
        });
    }

    public function down()
    {
        Schema::table('people', function (Blueprint $table) {
            foreach (['status', 'created_by'] as $col) {
                if (Schema::hasColumn('people', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddStatusToTitles extends Migration
{
    public function up()
    {
        Schema::table('titles', function (Blueprint $t) {
            if (!Schema::hasColumn('titles', 'status')) {
                // default 'approved' so existing TMDB library stays visible
                $t->string('status', 12)->default('approved')->index()->after('allow_update');
            }
            if (!Schema::hasColumn('titles', 'rejection_reason')) {
                $t->text('rejection_reason')->nullable()->after('status');
            }
        });

        // Be explicit on any rows that somehow ended up NULL.
        DB::table('titles')->whereNull('status')->update(['status' => 'approved']);
    }

    public function down()
    {
        Schema::table('titles', function (Blueprint $t) {
            if (Schema::hasColumn('titles', 'rejection_reason')) $t->dropColumn('rejection_reason');
            if (Schema::hasColumn('titles', 'status')) $t->dropColumn('status');
        });
    }
}

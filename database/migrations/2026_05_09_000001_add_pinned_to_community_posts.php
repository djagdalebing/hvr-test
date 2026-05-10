<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPinnedToCommunityPosts extends Migration
{
    public function up()
    {
        Schema::table('community_posts', function (Blueprint $t) {
            if (!Schema::hasColumn('community_posts', 'pinned')) {
                $t->boolean('pinned')->default(false)->index()->after('status');
            }
        });
    }

    public function down()
    {
        Schema::table('community_posts', function (Blueprint $t) {
            if (Schema::hasColumn('community_posts', 'pinned')) {
                $t->dropColumn('pinned');
            }
        });
    }
}

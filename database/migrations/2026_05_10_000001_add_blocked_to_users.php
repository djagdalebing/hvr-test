<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBlockedToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'blocked')) {
                $t->boolean('blocked')->default(false)->index()->after('role');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'blocked')) {
                $t->dropColumn('blocked');
            }
        });
    }
}

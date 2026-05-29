<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTrustedCreatorToUsers extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'trusted_creator')) {
                $t->boolean('trusted_creator')->default(false)->index()->after('blocked');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'trusted_creator')) {
                $t->dropColumn('trusted_creator');
            }
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The manually-created 'viewer' role was flagged guests=1 (and default=1),
 * so the admin Roles tab refused to list its members ("This role can't be
 * assigned to users") even though 7 users were correctly attached. Normalize
 * it to a regular assignable sitewide role. 'users' stays the sole default.
 */
class FixViewerRoleFlags extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('roles')) {
            return;
        }
        DB::table('roles')->where('name', 'viewer')->update([
            'guests'  => 0,
            'default' => 0,
            'type'    => 'sitewide',
        ]);
        DB::table('roles')->where('name', 'creator')->update([
            'guests'  => 0,
            'default' => 0,
            'type'    => 'sitewide',
        ]);
    }

    public function down()
    {
        // No-op.
    }
}

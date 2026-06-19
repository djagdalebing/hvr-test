<?php

use Common\Auth\Roles\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The viewer/creator distinction lives in the users.role column, but the
 * admin Roles tab is driven by the roles + user_role pivot. This backfills
 * that pivot so the 'viewer' and 'creator' roles actually list their members.
 * New users are kept in sync by User::syncAudienceRole() (called on register
 * and on 'Become a Creator').
 */
class SyncAudienceRoles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('user_role')) {
            return;
        }

        // Ensure both audience roles exist (reuse any the admin already made).
        $roleIds = [];
        foreach (['viewer', 'creator'] as $name) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['type' => 'sitewide', 'default' => false, 'guests' => false],
            );
            $roleIds[$name] = $role->id;
        }

        if (!Schema::hasColumn('users', 'role')) {
            return;
        }

        // Attach every user to the role matching their users.role value.
        DB::table('users')->select('id', 'role')->orderBy('id')->chunk(500, function ($users) use ($roleIds) {
            foreach ($users as $u) {
                $roleId = ($u->role === 'creator') ? $roleIds['creator'] : $roleIds['viewer'];
                if (!$roleId) continue;
                $exists = DB::table('user_role')
                    ->where('user_id', $u->id)->where('role_id', $roleId)->exists();
                if (!$exists) {
                    DB::table('user_role')->insert([
                        'user_id'    => $u->id,
                        'role_id'    => $roleId,
                        'created_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down()
    {
        // No-op: removing the pivot rows would just re-empty the roles.
    }
}

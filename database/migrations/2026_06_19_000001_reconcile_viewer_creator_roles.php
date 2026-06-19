<?php

use Common\Auth\Roles\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Earlier in the project signup briefly defaulted every account to
 * 'creator', so users.role is wrong for people who never actually became
 * creators — they all ended up in the Creator role. This reconciles the
 * truth: a user is a CREATOR only if they have a creator_profiles row OR
 * have uploaded a video; everyone else is a VIEWER. It then rewrites both
 * the users.role column and the viewer/creator user_role pivot to match.
 *
 * Going forward this stays correct on its own: new signups default to
 * viewer and 'Become a Creator' flips them to creator (both call
 * User::syncAudienceRole()).
 */
class ReconcileViewerCreatorRoles extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('user_role') || !Schema::hasColumn('users', 'role')) {
            return;
        }

        // Ensure the two audience roles exist and grab their ids.
        $roleIds = [];
        foreach (['viewer', 'creator'] as $name) {
            $role = Role::firstOrCreate(
                ['name' => $name],
                ['type' => 'sitewide', 'default' => false, 'guests' => false],
            );
            $roleIds[$name] = $role->id;
        }
        $viewerId  = $roleIds['viewer'];
        $creatorId = $roleIds['creator'];

        // Who is genuinely a creator: has a creator profile OR has uploaded
        // a video (you can only upload while role=creator, so this is a
        // safety net for any profile rows that failed to create historically).
        $creatorUserIds = collect();
        if (Schema::hasTable('creator_profiles')) {
            $creatorUserIds = $creatorUserIds->merge(
                DB::table('creator_profiles')->pluck('user_id'),
            );
        }
        if (Schema::hasTable('videos')) {
            $creatorUserIds = $creatorUserIds->merge(
                DB::table('videos')->whereNotNull('user_id')->pluck('user_id'),
            );
        }
        $creatorUserIds = $creatorUserIds->filter()->map(fn ($id) => (int) $id)->unique()->values();

        // 1) Fix the users.role column.
        if ($creatorUserIds->isNotEmpty()) {
            DB::table('users')->whereIn('id', $creatorUserIds)->update(['role' => 'creator']);
            DB::table('users')->whereNotIn('id', $creatorUserIds)->update(['role' => 'viewer']);
        } else {
            DB::table('users')->update(['role' => 'viewer']);
        }

        // 2) Rebuild the audience pivot from scratch (only the viewer/creator
        //    roles — the default 'Users' role and any others are left alone).
        DB::table('user_role')->whereIn('role_id', [$viewerId, $creatorId])->delete();

        $now = now();
        $allUserIds = DB::table('users')->pluck('id')->map(fn ($id) => (int) $id);
        $creatorSet = $creatorUserIds->flip();

        $rows = $allUserIds->map(function ($uid) use ($creatorSet, $viewerId, $creatorId, $now) {
            return [
                'user_id'    => $uid,
                'role_id'    => $creatorSet->has($uid) ? $creatorId : $viewerId,
                'created_at' => $now,
            ];
        });

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('user_role')->insert($chunk->values()->all());
        }
    }

    public function down()
    {
        // No-op: this only corrects bad data; reverting would re-introduce it.
    }
}

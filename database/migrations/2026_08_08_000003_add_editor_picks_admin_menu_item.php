<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add an "Editor's Picks" link to the admin sidebar menu so admins can reach
 * /admin/editor-picks. Clones the existing "Moderation" admin item (so the exact
 * structure/action format is preserved) and swaps label + the 'moderation'
 * segment of the action for 'editor-picks'. Idempotent.
 */
class AddEditorPicksAdminMenuItem extends Migration
{
    public function up()
    {
        $row = DB::table('settings')->where('name', 'menus')->first();
        if (!$row || !$row->value) {
            return;
        }
        $menus = json_decode($row->value, true);
        if (!is_array($menus)) {
            return;
        }

        $changed = false;

        foreach ($menus as &$menu) {
            if (empty($menu['items']) || !is_array($menu['items'])) {
                continue;
            }

            // already added? skip this menu
            foreach ($menu['items'] as $it) {
                if (isset($it['action']) && stripos((string) $it['action'], 'editor-picks') !== false) {
                    continue 2;
                }
            }

            // find the moderation admin item to clone + its index
            $modIndex = null;
            foreach ($menu['items'] as $i => $it) {
                if (isset($it['action']) && stripos((string) $it['action'], 'moderation') !== false) {
                    $modIndex = $i;
                    break;
                }
            }
            if ($modIndex === null) {
                continue;
            }

            $clone = $menu['items'][$modIndex];
            $clone['label']  = "Editor's Picks";
            $clone['action'] = str_ireplace('moderation', 'editor-picks', (string) $clone['action']);
            if (isset($clone['id'])) {
                unset($clone['id']); // let the app assign a fresh id if it uses one
            }

            array_splice($menu['items'], $modIndex + 1, 0, [$clone]);
            $changed = true;
        }
        unset($menu);

        if ($changed) {
            DB::table('settings')->where('name', 'menus')->update([
                'value' => json_encode($menus),
            ]);
        }
    }

    public function down()
    {
        // Menu edits are user-managed; intentionally not reversed.
    }
}

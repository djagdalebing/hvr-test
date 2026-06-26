<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The landing page now has its own "Explore the network" hero button, so
 * remove the duplicate menu item of the same name from any configured menu
 * (it lived in the landing navbar). Matches on label only, so nothing else
 * is touched.
 */
class RemoveExploreNetworkMenuItem extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

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
            $before = count($menu['items']);
            $menu['items'] = array_values(array_filter($menu['items'], function ($item) {
                $label = strtolower(trim((string) ($item['label'] ?? '')));
                return $label !== 'explore the network';
            }));
            if (count($menu['items']) !== $before) {
                $changed = true;
            }
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
        // No-op.
    }
}

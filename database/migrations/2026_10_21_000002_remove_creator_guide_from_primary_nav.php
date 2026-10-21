<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Take the creator guide back out of the primary navigation.
 *
 * The top bar is for viewers browsing content; a creator-facing explainer
 * does not belong there. The guide stays linked from the footer and from the
 * creator dashboard, which is where someone looking for it will be.
 */
class RemoveCreatorGuideFromPrimaryNav extends Migration
{
    public function up()
    {
        $row = DB::table('settings')->where('name', 'menus')->first();
        if (!$row) {
            return;
        }

        $menus = json_decode($row->value, true);
        if (!is_array($menus)) {
            return;
        }

        foreach ($menus as &$menu) {
            if (($menu['position'] ?? null) !== 'primary') {
                continue;
            }

            $menu['items'] = array_values(
                array_filter(
                    $menu['items'] ?? [],
                    fn($item) => ($item['action'] ?? null) !== '/creator-guide',
                ),
            );
        }
        unset($menu);

        DB::table('settings')
            ->where('name', 'menus')
            ->update(['value' => json_encode($menus)]);
    }

    public function down()
    {
        // Intentionally not reversible.
    }
}

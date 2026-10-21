<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair the footer links and surface the creator guide.
 *
 * Two sets of dead links:
 *
 *  - "About Us" and "FAQs" pointed at custom pages 42 and 43, which do not
 *    exist. Both 404. They now point at the creator guide, which is the page
 *    that actually answers what creators were asking.
 *
 *  - Explore pointed at lists 1-4, which were destroyed when a user who
 *    happened to own them was deleted. They now point at the computed
 *    homepage rows, which are derived from the catalogue and cannot be
 *    deleted with an account.
 */
class RepairFooterMenusAndAddGuide extends Migration
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

        $replacements = [
            'lists/1' => 'lists/hvn-highest-viewed',
            'lists/2' => 'lists/hvn-series',
            'lists/3' => 'lists/hvn-films',
            'lists/4' => 'lists/hvn-now-streaming',
            'pages/42/about-us' => '/creator-guide',
            'pages/43/faqs' => '/creator-guide',
        ];

        $labels = [
            'lists/hvn-highest-viewed' => 'Most Watched',
            'lists/hvn-series' => 'Indie Series',
            'lists/hvn-films' => 'Indie Films',
            'lists/hvn-now-streaming' => 'Now Streaming on HVN',
        ];

        foreach ($menus as &$menu) {
            if (empty($menu['items']) || !is_array($menu['items'])) {
                continue;
            }

            foreach ($menu['items'] as &$item) {
                $action = $item['action'] ?? null;
                if (!$action || !isset($replacements[$action])) {
                    continue;
                }

                $item['action'] = $replacements[$action];

                if (isset($labels[$item['action']])) {
                    $item['label'] = $labels[$item['action']];
                } elseif ($action === 'pages/42/about-us') {
                    $item['label'] = 'How HVN Works';
                } elseif ($action === 'pages/43/faqs') {
                    $item['label'] = 'Creator FAQ';
                }

                // The computed rows and the guide are plain routes, not
                // custom-page references.
                $item['type'] = 'link';
            }
            unset($item);
        }
        unset($menu);

        // Put the guide in the primary nav too -- a creator should not have to
        // reach the footer to find out how any of this works.
        foreach ($menus as &$menu) {
            if (($menu['position'] ?? null) !== 'primary') {
                continue;
            }

            $already = collect($menu['items'] ?? [])->contains(
                fn($i) => ($i['action'] ?? null) === '/creator-guide',
            );
            if (!$already) {
                $menu['items'][] = [
                    'type' => 'link',
                    'condition' => null,
                    'target' => null,
                    'activeExact' => false,
                    'order' => count($menu['items'] ?? []) + 1,
                    'label' => 'For Creators',
                    'action' => '/creator-guide',
                    'id' => 9101,
                ];
            }
        }
        unset($menu);

        DB::table('settings')
            ->where('name', 'menus')
            ->update(['value' => json_encode($menus)]);
    }

    public function down()
    {
        // Not reversible: the targets this replaces were already 404s.
    }
}

<?php

namespace App\Http\Controllers;

use App\ListModel;
use App\Services\Hvn\HvnHomepageSections;
use App\Services\Lists\LoadListContent;
use Common\Core\BaseController;
use Common\Settings\Settings;

class HomepageContentController extends BaseController
{
    /**
     * @var ListModel
     */
    private $list;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var HvnHomepageSections
     */
    private $sections;

    public function __construct(
        ListModel $list,
        Settings $settings,
        HvnHomepageSections $sections
    ) {
        $this->list = $list;
        $this->settings = $settings;
        $this->sections = $sections;
    }

    public function show()
    {
        $homepageLists = $this->settings->getJson('homepage.lists');
        if (!$homepageLists) {
            return ['lists' => []];
        }

        $lists = $this->list
            ->whereIn('id', $homepageLists)
            ->where('system', false)
            ->get();
        $itemCount = $this->settings->get('homepage.list_items_count', 10);
        $sliderItemCount = $this->settings->get(
            'homepage.slider_items_count',
            5,
        );

        // sort lists by order specified in settings
        $lists = $lists
            ->sortBy(function ($model) use ($homepageLists) {
                return array_search($model->id, $homepageLists);
            })
            ->values();

        $lists = $lists->map(function (ListModel $list, $index) use (
            $itemCount,
            $sliderItemCount,
            $homepageLists
        ) {
            $list->items = app(LoadListContent::class)->execute($list, [
                'limit' =>
                    $index === 0 ? $sliderItemCount : min($itemCount, 30),
            ]);
            return $list;
        });

        // HVN computed homepage rows. Each is capped at 10 and hidden when it
        // has no titles. Insert them right after the "Now Streaming on HVN" row
        // if present, otherwise append to the end.
        $lists = $lists->values()->all();
        $sections = array_values(array_filter(
            $this->hvnSections(),
            fn($s) => $s['items']->isNotEmpty(),
        ));
        if (!empty($sections)) {
            $anchor = null;
            foreach ($lists as $i => $l) {
                if (strcasecmp((string) ($l->name ?? ''), 'Now Streaming on HVN') === 0) {
                    $anchor = $i;
                    break;
                }
            }
            if ($anchor !== null) {
                array_splice($lists, $anchor + 1, 0, $sections);
            } else {
                $lists = array_merge($lists, $sections);
            }
        }

        $options = [
            'prerender' => [
                'view' => 'home.show',
                'config' => 'home.show',
            ],
        ];

        return $this->success(['lists' => $lists], 200, $options);
    }

    /**
     * The three computed HVN homepage rows, built by the shared service so the
     * same sections can also render a full "showcase" page via /lists/{slug}.
     */
    private function hvnSections(): array
    {
        // Homepage carousels are capped at 10; the full showcase page
        // (/lists/{slug}) shows more.
        $out = [];
        foreach (array_keys($this->sections->definitions()) as $slug) {
            $out[] = $this->sections->section($slug, 10);
        }
        return $out;
    }
}

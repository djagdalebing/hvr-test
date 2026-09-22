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
        // NB: deliberately no early return when homepage.lists is empty. The
        // computed HVN rows below do not depend on it, and bailing out here
        // meant that deleting the user who happened to own the curated lists
        // took the entire homepage down with them -- DeleteLists strips the
        // ids from this setting, and every row vanished, computed ones
        // included.
        $homepageLists = $this->settings->getJson('homepage.lists') ?: [];

        $lists = $homepageLists
            ? $this->list
                ->whereIn('id', $homepageLists)
                ->where('system', false)
                ->get()
            : collect();
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
            $list->items = $this->onlyPublished(
                app(LoadListContent::class)->execute($list, [
                    'limit' =>
                        $index === 0 ? $sliderItemCount : min($itemCount, 30),
                ]),
            );
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
    /**
     * Drop anything that is not publicly visible from a homepage row.
     *
     * Title's 'approved' global scope exempts admins and the creator who
     * uploaded a title, which is right for the moderation screen and the
     * title page but wrong here: the homepage is a curated public surface and
     * should look the same to everyone. Without this an admin sees rejected
     * titles sitting in "Highest Viewed" and in manually curated rows.
     *
     * Imported catalogue titles predate the status column and carry NULL, so
     * NULL counts as published.
     */
    private function onlyPublished($items)
    {
        return $items
            ->filter(function ($item) {
                if (!array_key_exists('status', $item->getAttributes())) {
                    return true; // not a Title (person, episode, ...)
                }
                $status = $item->getAttributes()['status'];
                return $status === null || $status === 'approved';
            })
            ->values();
    }

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

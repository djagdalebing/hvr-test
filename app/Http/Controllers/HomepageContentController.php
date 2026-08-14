<?php

namespace App\Http\Controllers;

use App\ListModel;
use App\Services\Lists\LoadListContent;
use App\Title;
use App\Video;
use Common\Core\BaseController;
use Common\Settings\Settings;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Str;

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

    public function __construct(ListModel $list, Settings $settings)
    {
        $this->list = $list;
        $this->settings = $settings;
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
     * The three HVN homepage rows: recently-approved platform originals,
     * admin-pinned editor's picks, and the most-viewed titles.
     */
    private function hvnSections(): array
    {
        return [
            [
                'id'    => 'hvn-exclusive',
                'name'  => 'Exclusive Content Release',
                'style' => 'portrait',
                'items' => $this->loadTitlesByIds($this->exclusiveIds()),
            ],
            [
                'id'    => 'hvn-editor-picks',
                'name'  => "Editor's Pick",
                'style' => 'portrait',
                'items' => $this->loadTitlesByIds($this->editorPickIds()),
            ],
            [
                'id'    => 'hvn-highest-viewed',
                'name'  => 'Highest Viewed',
                'style' => 'portrait',
                'items' => $this->loadTitlesByIds($this->highestViewedIds()),
            ],
        ];
    }

    /** Newest-approved-first titles uploaded by creators on this platform. */
    private function exclusiveIds(): array
    {
        return Title::where('status', 'approved')
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('videos')
                    ->whereColumn('videos.title_id', 'titles.id')
                    ->whereNotNull('videos.user_id')
                    ->where('videos.approved', 1);
            })
            ->orderByRaw('COALESCE(approved_at, created_at) DESC')
            ->limit(10)
            ->pluck('id')
            ->all();
    }

    /** Admin-pinned editor's picks (ordered, max 10). */
    private function editorPickIds(): array
    {
        $raw = $this->settings->get('homepage.editor_picks');
        if (is_string($raw)) $raw = json_decode($raw, true);
        if (!is_array($raw)) return [];
        return array_slice(array_values(array_filter(array_map('intval', $raw))), 0, 10);
    }

    /** Most-viewed titles. */
    private function highestViewedIds(): array
    {
        return Title::orderBy('views', 'desc')
            ->limit(10)
            ->pluck('id')
            ->all();
    }

    /**
     * Load Title models for the given ordered ids, shaped like normal list
     * items (same select fields + genres + videos) so <media-view> renders
     * them identically. Order is preserved and anything not visible to the
     * current viewer (global 'approved' scope) is dropped.
     */
    private function loadTitlesByIds(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $preferFull = $this->settings->get('streaming.prefer_full');

        $titles = Title::whereIn('id', $ids)->get([
            'id', 'name', 'poster', 'description', 'is_series', 'year',
            'tmdb_vote_average', 'backdrop', 'runtime', 'release_date',
            'popularity', 'local_vote_average',
        ]);

        $titles->load([
            'genres',
            'videos' => function (HasMany $query) use ($titles, $preferFull) {
                $query
                    ->where('type', '!=', 'external')
                    ->where('category', $preferFull ? '=' : '!=', 'full')
                    ->groupBy('title_id');
                if (!$preferFull) {
                    $query->limit($titles->count());
                }
            },
        ]);

        return $titles
            ->sortBy(fn($t) => array_search($t->id, $ids))
            ->values()
            ->map(function ($t) {
                $t->description = Str::limit($t->description, 600);
                return $t;
            });
    }
}

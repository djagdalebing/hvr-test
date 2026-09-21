<?php

namespace App\Services\Hvn;

use App\Title;
use Common\Settings\Settings;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Str;

/**
 * Builds the computed HVN homepage rows (Exclusive Content Release, Editor's
 * Pick, Highest Viewed). Shared by:
 *  - HomepageContentController (renders them as homepage carousels), and
 *  - ListController@show (renders a full "showcase" page for a section when
 *    its header is clicked, e.g. /lists/hvn-highest-viewed).
 */
class HvnHomepageSections
{
    /**
     * @var Settings
     */
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Metadata for each computed section, keyed by slug. The description is the
     * small subtext shown under the section title (matching normal lists).
     */
    public function definitions(): array
    {
        return [
            'hvn-exclusive' => [
                'name' => 'Exclusive Content Release',
                'description' => 'Fresh titles just released by creators on Her Vision Network.',
                'style' => 'portrait',
            ],
            'hvn-editor-picks' => [
                'name' => "Editor's Pick",
                'description' => 'Hand-picked highlights chosen by the HVN team.',
                'style' => 'portrait',
            ],
            'hvn-highest-viewed' => [
                'name' => 'Highest Viewed',
                'description' => 'The most-watched titles on Her Vision Network right now.',
                'style' => 'portrait',
            ],
            'hvn-poc' => [
                'name' => 'Proof of Concept',
                'description' => 'Early proof-of-concept pitches and demos from HVN creators.',
                'style' => 'portrait',
            ],
        ];
    }

    public function isSection(string $slug): bool
    {
        return array_key_exists($slug, $this->definitions());
    }

    /**
     * Build a single section: id, name, description, style and its items.
     * Returns null for an unknown slug.
     */
    public function section(string $slug, int $limit = 10): ?array
    {
        $defs = $this->definitions();
        if (!isset($defs[$slug])) {
            return null;
        }

        return array_merge(
            ['id' => $slug],
            $defs[$slug],
            ['items' => $this->loadTitles($this->idsFor($slug, $limit))],
        );
    }

    public function idsFor(string $slug, int $limit): array
    {
        switch ($slug) {
            case 'hvn-exclusive':
                return $this->exclusiveIds($limit);
            case 'hvn-editor-picks':
                return $this->editorPickIds($limit);
            case 'hvn-highest-viewed':
                return $this->highestViewedIds($limit);
            case 'hvn-poc':
                return $this->pocIds($limit);
            default:
                return [];
        }
    }

    /** Approved Proof-of-Concept titles, newest first. */
    private function pocIds(int $limit): array
    {
        return Title::where('type', 'poc')
            ->where('status', 'approved')
            ->orderByRaw('COALESCE(approved_at, created_at) DESC')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    /** Newest-approved-first titles uploaded by creators on this platform. */
    private function exclusiveIds(int $limit): array
    {
        return Title::where('status', 'approved')
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('videos')
                    ->whereColumn('videos.title_id', 'titles.id')
                    ->whereNotNull('videos.user_id')
                    ->where('videos.approved', 1);
            })
            ->orderByRaw('COALESCE(approved_at, created_at) DESC')
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    /** Admin-pinned editor's picks (ordered). */
    private function editorPickIds(int $limit): array
    {
        $raw = $this->settings->get('homepage.editor_picks');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        return array_slice(array_values(array_filter(array_map('intval', $raw))), 0, $limit);
    }

    /** Most-viewed titles. */
    private function highestViewedIds(int $limit): array
    {
        return $this->onlyPublished(Title::orderBy('views', 'desc'))
            ->limit($limit)
            ->pluck('id')
            ->all();
    }

    /**
     * The same visibility predicate as Title's 'approved' global scope, but
     * without its admin and creator-owner exemptions.
     *
     * Homepage rows are a curated public surface, so an admin or the
     * uploading creator should see exactly what a viewer sees there rather
     * than their own pending or rejected titles -- previewing those belongs
     * on the moderation screen and the title page, not in "Highest Viewed".
     *
     * Imported catalogue titles predate the status column and carry NULL, so
     * NULL counts as visible; filtering on status = 'approved' alone would
     * drop most of the library.
     */
    private function onlyPublished($query)
    {
        return $query->where(function ($w) {
            $w->where('titles.status', 'approved')->orWhereNull(
                'titles.status',
            );
        });
    }

    /**
     * Load Title models for the given ordered ids, shaped like normal list
     * items (same select fields + genres + videos). Order is preserved and
     * anything not publicly published is dropped -- see onlyPublished(), which
     * is stricter than the global scope on purpose.
     */
    public function loadTitles(array $ids): Collection
    {
        if (empty($ids)) {
            return collect();
        }

        $preferFull = $this->settings->get('streaming.prefer_full');

        // Safety net for every section, not just the computed ones: an
        // editor's pick that was later rejected must drop out of the row too.
        $titles = $this->onlyPublished(Title::whereIn('id', $ids))->get([
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

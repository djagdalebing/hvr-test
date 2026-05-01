<?php

namespace App\Http\Controllers;

use App\Services\Data\Local\LocalDataProvider;
use App\Services\Data\Tmdb\TmdbApi;
use App\Title;
use Common\Core\BaseController;
use Common\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Str;

class SearchController extends BaseController
{
    /**
     * @var Request
     */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function index($query)
    {
        $dataProvider =
            $this->request->get('provider') ?:
            app(Settings::class)->get('content.search_provider');
        $results = $this->searchUsing($dataProvider, $query);

        $results = $results
            ->map(function ($result) {
                if (isset($result['description'])) {
                    $result['description'] = Str::limit(
                        $result['description'],
                        170,
                    );
                }
                return $result;
            })
            ->values();

        return $this->success([
            'results' => $results,
            'query' => trim(strip_tags($query), '"\''),
        ]);
    }

    private function searchUsing($provider, $query)
    {
        if ($provider === 'tmdb') {
            $results = app(TmdbApi::class)->search($query, $this->request->all());
            // Creator-uploaded titles only exist locally — always include them
            // so they're findable regardless of the configured search provider.
            return $this->prependCreatorTitles($query, $results);
        }

        $results = app(LocalDataProvider::class)->search(
            $query,
            $this->request->all(),
        );

        if ($provider === 'all') {
            $tmdb = app(TmdbApi::class)->search($query, $this->request->all());
            $results = $results
                ->concat($tmdb)
                ->unique(function ($item) {
                    return ($item['tmdb_id'] ?: $item['name']) . $item['model_type'];
                })
                ->groupBy('model_type')
                // make sure specified limit is enforced per group
                // (title, person) instead of the whole collection
                ->map(function (Collection $group) {
                    return $group->slice(0, $this->request->get('limit', 8));
                })
                ->flatten(1)
                ->values();
        }

        return $results;
    }

    /**
     * Prepend any creator-uploaded titles matching the query so they remain
     * findable even when the active search provider is TMDB-only.
     */
    private function prependCreatorTitles($query, $results): Collection
    {
        $q = trim((string) $query);
        if ($q === '') {
            return collect($results);
        }

        $creator = Title::whereHas('videos', function ($v) {
                $v->whereNotNull('user_id');
            })
            ->where(function ($b) use ($q) {
                $b->where('name', 'like', "%$q%")
                  ->orWhere('original_title', 'like', "%$q%");
            })
            ->orderByRaw(
                'CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
                [$q, "$q%"]
            )
            ->take(8)
            ->get();

        if ($creator->isEmpty()) {
            return collect($results);
        }

        return $creator
            ->concat($results)
            ->unique(function ($item) {
                return ($item['tmdb_id'] ?? null) ?: ($item['name'] ?? '') . ($item['model_type'] ?? '');
            })
            ->values();
    }
}

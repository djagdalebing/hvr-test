<?php

namespace App\Http\Controllers;

use App\Episode;
use App\Season;
use App\Title;
use App\Video;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CreatorContentController extends BaseController
{
    /**
     * GET /api/v1/creator/content
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated.'], 401);
        $userId = $user->id;

        $titles = Title::withoutGlobalScope('approved')
            ->whereHas('videos', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with(['videos' => function ($q) use ($userId) {
                $q->where('user_id', $userId)->select('id', 'title_id', 'url', 'type', 'category', 'source');
            }, 'credits'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->success(['titles' => $titles]);
    }

    /**
     * POST /api/v1/creator/content
     * Accepts multipart/form-data with optional video_file and cover image.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }

        $this->validate($request, [
            'title'          => 'required|string|min:2|max:250',
            'type'           => 'required|in:movie,short,series,documentary,poc',
            'year'           => 'nullable|integer|min:1900|max:2099',
            'description'    => 'nullable|string|max:5000',
            'tagline'        => 'nullable|string|max:250',
            'runtime'        => 'nullable|integer|min:1|max:1440',
            'genre'          => 'nullable|string|max:255',
            'language'       => 'nullable|string|max:50',
            'country'        => 'nullable|string|max:80',
            'release_date'   => 'nullable|date',
            'certification'  => 'nullable|string|max:20',
            'original_title' => 'nullable|string|max:250',
            'trailer'        => 'nullable|string|max:1000',
            // Financials + external IDs (Phase 1 metadata)
            'budget'         => 'nullable|integer|min:0|max:99999999999',
            'revenue'        => 'nullable|integer|min:0|max:99999999999',
            'imdb_id'        => 'nullable|string|max:20|regex:/^tt\d{6,}$/',
            'tmdb_id'        => 'nullable|integer|min:1',
            // cast is a JSON array of {name, character?}; we parse + attach
            // Person records via the creditables polymorphic table.
            'cast'           => 'nullable|string|max:8000',
            'director'       => 'nullable|string|max:255',
            'writer'         => 'nullable|string|max:255',
            'video_url'      => 'nullable|string|max:1000',
            // r2_video_url is the public URL of a file the browser already
            // uploaded straight to Cloudflare R2 via a presigned PUT.
            'r2_video_url'   => 'nullable|string|max:1000',
            // MP4/WebM/OGG play in every browser. QuickTime (.mov / .m4v) is
            // accepted too — it's usually H.264 and plays in Safari + modern
            // Chrome, though not everywhere (no transcoding yet), so it's
            // best-effort. Other containers (.avi/.mkv/…) still can't play.
            'video_file'     => 'nullable|file|mimetypes:video/mp4,video/webm,video/ogg,video/quicktime,video/x-m4v|max:512000',
            'cover'          => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'backdrop_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        if (!$request->filled('video_url') && !$request->filled('r2_video_url') && !$request->hasFile('video_file')) {
            return response()->json([
                'errors' => ['video_url' => ['Provide a video URL, upload a file, or upload to cloud storage.']],
            ], 422);
        }

        // Store relative paths (no leading slash). Vebto's SPA concatenates
        // baseUrl + value, so '/storage/x' would produce 'host.com//storage/x'
        // (doubled slash → 422 at the edge).
        $posterPath  = $request->file('cover')->store('creator_content/covers', 'public');
        $posterUrl   = 'storage/' . $posterPath;
        $backdropUrl = $posterUrl; // mirror poster if no separate hero image
        if ($request->hasFile('backdrop_image')) {
            $backdropPath = $request->file('backdrop_image')->store('creator_content/backdrops', 'public');
            $backdropUrl  = 'storage/' . $backdropPath;
        }

        $type = $request->input('type');

        // Every creator submission — trusted creators included — starts PENDING
        // and must be approved by an admin before it goes live. Auto-approving
        // trusted creators put content live without review and also created an
        // "approved title / unapproved video" mismatch, because the title status
        // and the video's `approved` flag are gated separately. Keeping both
        // pending here means an admin approval (apiApproveContent) is the single
        // point that flips the title AND its videos live, in sync.
        $initialStatus = 'pending';

        $record = new Title();
        $record->name           = $request->input('title');
        $record->type           = $type;
        $record->year           = $request->input('year');
        $record->description    = $request->input('description');
        $record->tagline        = $request->input('tagline');
        $record->runtime        = $request->input('runtime');
        $record->genre          = $request->input('genre');
        $record->language       = $request->input('language');
        $record->country        = $request->input('country');
        $record->release_date   = $request->input('release_date');
        $record->certification  = $request->input('certification');
        $record->original_title = $request->input('original_title');
        if ($request->filled('trailer')) {
            $record->trailer = $request->input('trailer');
        }
        // Phase 1 metadata — financials + external IDs (all nullable).
        if ($request->filled('budget'))  $record->budget  = (int) $request->input('budget');
        if ($request->filled('revenue')) $record->revenue = (int) $request->input('revenue');
        if ($request->filled('imdb_id')) $record->imdb_id = $request->input('imdb_id');
        if ($request->filled('tmdb_id')) $record->tmdb_id = (int) $request->input('tmdb_id');
        $record->poster       = $posterUrl;
        $record->backdrop     = $backdropUrl;
        $record->adult        = false;
        $record->is_series    = ($type === 'series') ? true : false;
        $record->popularity   = 1;
        $record->fully_synced = true;
        $record->allow_update = false;
        $record->status       = $initialStatus;
        $record->save();

        // ---- Cast & crew (find_or_create Person, attach as credit) ----
        $this->attachCredits($record, $request);

        [$videoUrl, $source, $videoType] = $this->resolveUploadedVideo($request);

        Video::create([
            'title_id' => $record->id,
            'user_id'  => $user->id,
            'name'     => $record->name,
            'url'      => $videoUrl,
            'type'     => $videoType,
            'category' => 'full',
            'language' => 'en',
            'source'   => $source,
            // Track moderation state on the video too so /admin/videos
            // reflects pending (cross) vs approved (tick) instead of
            // always showing a tick.
            'approved' => ($initialStatus === 'approved') ? 1 : 0,
            'order'    => 1,
        ]);

        // A series is not a single video. The submission carries episode 1, so
        // scaffold season 1 around it here and let the creator add the rest
        // from "Manage episodes" -- otherwise picking Series in the dropdown
        // produced a title flagged is_series with exactly one unreachable
        // video and no way to ever add a second.
        if ($type === Title::SERIES_TYPE) {
            $this->scaffoldFirstEpisode($record, $request);
        }

        // Notify admins when a fresh upload is awaiting review. Trusted
        // creators (auto-approved) skip the alert.
        if ($initialStatus === 'pending') {
            \App\User::notifyAdmins(new \App\Notifications\HvnContentSubmitted($record, $user));
        }

        return response()->json(['title' => $record], 201);
    }

    /**
     * Work out where a submitted video actually lives.
     *
     * Shared by the initial submission and by each added episode so the three
     * upload routes (direct-to-R2, server upload, external link) behave
     * identically wherever a creator supplies a video.
     *
     * @return array{0: string, 1: string, 2: string} [url, source, type]
     */
    private function resolveUploadedVideo(Request $request): array
    {
        if ($request->filled('r2_video_url')) {
            // Browser uploaded straight to Cloudflare R2; we just store the URL.
            return [
                $request->input('r2_video_url'),
                'r2',
                Video::VIDEO_TYPE_DIRECT,
            ];
        }

        if ($request->hasFile('video_file')) {
            $videoPath = $request
                ->file('video_file')
                ->store('creator_content/videos', 'public');
            // Self-hosted MP4/WebM file → play directly in <video> tag.
            return ['/storage/' . $videoPath, 'local', Video::VIDEO_TYPE_DIRECT];
        }

        $videoUrl = $request->input('video_url');

        // YouTube/Vimeo/etc embed iframes; raw .mp4 URLs play directly.
        return [$videoUrl, 'external', $this->detectExternalType($videoUrl)];
    }

    /**
     * Turn the video submitted with a new series into season 1, episode 1.
     *
     * Best-effort: if this fails the series still exists as a title, which is
     * recoverable from the admin area -- better than failing the whole
     * submission after the creator has already uploaded the file.
     */
    private function scaffoldFirstEpisode(Title $title, Request $request): void
    {
        try {
            $season = Season::create([
                'title_id' => $title->id,
                'number' => 1,
                'episode_count' => 1,
                'allow_update' => 0,
            ]);

            $episode = Episode::create([
                'title_id' => $title->id,
                'season_id' => $season->id,
                'season_number' => 1,
                'episode_number' => 1,
                'title' => $request->input('episode_title') ?: 'Episode 1',
                'plot' => $request->input('description'),
                'allow_update' => 0,
            ]);

            Video::where('title_id', $title->id)->update([
                'season_num' => 1,
                'episode_num' => 1,
                'episode_id' => $episode->id,
            ]);

            $title->season_count = 1;
            $title->episode_count = 1;
            $title->save();
        } catch (\Throwable $e) {
            \Log::warning(
                'scaffoldFirstEpisode failed for title ' .
                    $title->id .
                    ': ' .
                    $e->getMessage(),
            );
        }
    }

    /**
     * Resolve the current creator and assert they own this series.
     *
     * Ownership is derived from videos.user_id, the same anchor update() and
     * destroy() use -- titles carry no owner column of their own.
     *
     * @return array{0: \App\User, 1: Title}|JsonResponse
     */
    private function requireOwnedSeries(Request $request, int $titleId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }

        $owns = Video::where('title_id', $titleId)
            ->where('user_id', $user->id)
            ->exists();
        if (!$owns) {
            return response()->json(['message' => 'Not found or unauthorized.'], 404);
        }

        $title = Title::withoutGlobalScope('approved')->find($titleId);
        if (!$title) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        if ($title->type !== Title::SERIES_TYPE) {
            return response()->json(
                ['message' => 'Episodes can only be added to a series.'],
                422,
            );
        }

        return [$user, $title];
    }

    /**
     * GET /secure/creator/content/{id}/episodes
     *
     * Seasons and episodes for a series the creator owns, each episode
     * carrying its own review state so the dashboard can show which ones are
     * live and which are still waiting.
     */
    public function episodes(Request $request, int $titleId): JsonResponse
    {
        $resolved = $this->requireOwnedSeries($request, $titleId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        [$user, $title] = $resolved;

        $episodes = Episode::where('title_id', $titleId)
            ->orderBy('season_number')
            ->orderBy('episode_number')
            ->get(['id', 'title', 'plot', 'season_number', 'episode_number']);

        $videos = Video::where('title_id', $titleId)
            ->whereNotNull('episode_num')
            ->get(['id', 'episode_id', 'url', 'type', 'source', 'approved'])
            ->keyBy('episode_id');

        $rows = $episodes->map(function ($ep) use ($videos) {
            $video = $videos->get($ep->id);
            return [
                'id' => $ep->id,
                'title' => $ep->title,
                'plot' => $ep->plot,
                'season_number' => $ep->season_number,
                'episode_number' => $ep->episode_number,
                'has_video' => (bool) $video,
                // Per-episode review state. A new episode on an already
                // approved series stays hidden until an admin approves it,
                // so the series keeps playing while the new one waits.
                'approved' => $video ? (bool) $video->approved : false,
            ];
        });

        return response()->json([
            'title' => [
                'id' => $title->id,
                'name' => $title->name,
                'status' => $title->status,
            ],
            'seasons' => Season::where('title_id', $titleId)
                ->orderBy('number')
                ->get(['id', 'number', 'title']),
            'episodes' => $rows,
        ]);
    }

    /**
     * POST /secure/creator/content/{id}/episodes
     *
     * Add one episode plus its video. Creating the season on demand keeps the
     * creator from having to manage seasons as a separate concept -- they
     * pick a season number and it exists.
     */
    public function storeEpisode(Request $request, int $titleId): JsonResponse
    {
        $resolved = $this->requireOwnedSeries($request, $titleId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        [$user, $title] = $resolved;

        $request->validate([
            'season_number' => 'required|integer|min:1|max:100',
            'episode_number' => 'nullable|integer|min:1|max:1000',
            'episode_title' => 'nullable|string|max:255',
            'plot' => 'nullable|string|max:5000',
            'video_url' => 'nullable|string|max:1000',
            'r2_video_url' => 'nullable|string|max:1000',
            'video_file' =>
                'nullable|file|mimetypes:video/mp4,video/webm,video/ogg,video/quicktime,video/x-m4v|max:512000',
        ]);

        if (
            !$request->filled('video_url') &&
            !$request->filled('r2_video_url') &&
            !$request->hasFile('video_file')
        ) {
            return response()->json(
                [
                    'errors' => [
                        'video_url' => [
                            'Provide a video URL, upload a file, or upload to cloud storage.',
                        ],
                    ],
                ],
                422,
            );
        }

        $seasonNumber = (int) $request->input('season_number');

        $season = Season::where('title_id', $titleId)
            ->where('number', $seasonNumber)
            ->first();
        if (!$season) {
            $season = Season::create([
                'title_id' => $titleId,
                'number' => $seasonNumber,
                'episode_count' => 0,
                'allow_update' => 0,
            ]);
        }

        // Default to the next free slot in that season so the common case
        // (just add the next one) needs no thought from the creator.
        $episodeNumber = (int) $request->input('episode_number');
        if ($episodeNumber < 1) {
            $episodeNumber =
                (int) Episode::where('title_id', $titleId)
                    ->where('season_number', $seasonNumber)
                    ->max('episode_number') + 1;
        }

        $clash = Episode::where('title_id', $titleId)
            ->where('season_number', $seasonNumber)
            ->where('episode_number', $episodeNumber)
            ->exists();
        if ($clash) {
            return response()->json(
                [
                    'errors' => [
                        'episode_number' => [
                            "Season {$seasonNumber} already has an episode {$episodeNumber}.",
                        ],
                    ],
                ],
                422,
            );
        }

        [$videoUrl, $source, $videoType] = $this->resolveUploadedVideo($request);

        $episode = Episode::create([
            'title_id' => $titleId,
            'season_id' => $season->id,
            'season_number' => $seasonNumber,
            'episode_number' => $episodeNumber,
            'title' =>
                $request->input('episode_title') ?: "Episode {$episodeNumber}",
            'plot' => $request->input('plot'),
            'allow_update' => 0,
        ]);

        Video::create([
            'title_id' => $titleId,
            'episode_id' => $episode->id,
            'user_id' => $user->id,
            'name' => $episode->title,
            'url' => $videoUrl,
            'type' => $videoType,
            'category' => 'full',
            'language' => 'en',
            'source' => $source,
            'season_num' => $seasonNumber,
            'episode_num' => $episodeNumber,
            // Always unapproved. An episode added to a live series must not
            // go out unreviewed, and the series itself keeps playing while
            // this one waits -- that is the whole point of gating per video
            // rather than flipping the title back to pending.
            'approved' => 0,
            'order' => $episodeNumber,
        ]);

        $season->episode_count = Episode::where('title_id', $titleId)
            ->where('season_number', $seasonNumber)
            ->count();
        $season->save();

        $title->season_count = Season::where('title_id', $titleId)->count();
        $title->episode_count = Episode::where('title_id', $titleId)->count();
        $title->save();

        try {
            \App\User::notifyAdmins(
                new \App\Notifications\HvnContentSubmitted($title, $user),
            );
        } catch (\Throwable $e) {
            \Log::warning('Episode submit notify failed: ' . $e->getMessage());
        }

        return response()->json(['episode' => $episode], 201);
    }

    /**
     * DELETE /secure/creator/content/{id}/episodes/{episodeId}
     */
    public function destroyEpisode(
        Request $request,
        int $titleId,
        int $episodeId
    ): JsonResponse {
        $resolved = $this->requireOwnedSeries($request, $titleId);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        [$user, $title] = $resolved;

        $episode = Episode::where('title_id', $titleId)
            ->where('id', $episodeId)
            ->first();
        if (!$episode) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $videos = Video::where('title_id', $titleId)
            ->where('episode_id', $episodeId)
            ->get();

        // Drop the stored media too, so deleting an episode does not leave
        // its file paid for in the bucket forever.
        app(\App\Services\Hvn\DeleteVideoMedia::class)->execute($videos);
        Video::where('title_id', $titleId)
            ->where('episode_id', $episodeId)
            ->delete();

        $seasonNumber = $episode->season_number;
        $episode->delete();

        Season::where('title_id', $titleId)
            ->where('number', $seasonNumber)
            ->update([
                'episode_count' => Episode::where('title_id', $titleId)
                    ->where('season_number', $seasonNumber)
                    ->count(),
            ]);

        $title->episode_count = Episode::where('title_id', $titleId)->count();
        $title->save();

        return response()->json(['deleted' => true]);
    }

    /**
     * POST /secure/creator/content/presign
     * Returns a presigned PUT URL so the browser can upload a large
     * video straight to Cloudflare R2, plus the public URL the file
     * will be reachable at afterwards. Nothing about the file passes
     * through PHP, so the server upload-size limit never applies.
     */
    public function presign(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }

        if (!config('filesystems.disks.r2.bucket') || !config('filesystems.disks.r2.endpoint')) {
            return response()->json(['message' => 'Cloud storage is not configured.'], 503);
        }

        $this->validate($request, [
            'filename'     => 'required|string|max:255',
            // See the video_file rule in store(). MP4/WebM/OGG play everywhere;
            // QuickTime (.mov/.m4v) accepted best-effort.
            'content_type' => 'required|string|in:video/mp4,video/webm,video/ogg,video/quicktime,video/x-m4v',
        ], [
            'content_type.in' => 'Please upload MP4, WebM, OGG, or MOV. MP4 plays in every browser; MOV may not play for all viewers. (AVI/MKV aren\'t supported — convert to MP4 first.)',
        ]);

        $ext = pathinfo($request->input('filename'), PATHINFO_EXTENSION);
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext) ?: 'mp4';
        $key = 'creator_content/videos/' . $user->id . '/' . \Str::random(40) . '.' . strtolower($ext);

        try {
            /** @var \League\Flysystem\AwsS3v3\AwsS3Adapter $adapter */
            $adapter = Storage::disk('r2')->getAdapter();
            $client  = $adapter->getClient();
            $bucket  = config('filesystems.disks.r2.bucket');

            $cmd = $client->getCommand('PutObject', [
                'Bucket'      => $bucket,
                'Key'         => $key,
                'ContentType' => $request->input('content_type'),
            ]);
            $presigned = $client->createPresignedRequest($cmd, '+30 minutes');

            $base = rtrim((string) config('filesystems.disks.r2.url'), '/');

            return response()->json([
                'upload_url'   => (string) $presigned->getUri(),
                'public_url'   => $base . '/' . $key,
                'key'          => $key,
                'content_type' => $request->input('content_type'),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('R2 presign failed: ' . $e->getMessage());
            return response()->json(['message' => 'Could not prepare upload.'], 500);
        }
    }

    /**
     * Choose the right video type for an external URL so the player
     * picks the correct renderer (iframe embed vs <video> tag).
     */
    /**
     * Attach cast + crew to the title via the creditables pivot.
     *
     * Phase 2 picker format: each cast row is {person_id?, name?, character?}
     * and director/writer come as director_id / writer_id (preferred) or a
     * plain director / writer name string (legacy fallback). When a person_id
     * is given we resolve the existing Person (bypassing the moderation scope
     * so a just-created pending person still attaches); otherwise we fall back
     * to firstOrCreate by name for backward compatibility.
     * Best-effort — failures are logged but never bubble up to the user.
     */
    private function attachCredits(\App\Title $title, Request $request): void
    {
        try {
            // CAST
            $castRaw = $request->input('cast');
            $cast = [];
            if (is_string($castRaw) && $castRaw !== '') {
                $decoded = json_decode($castRaw, true);
                if (is_array($decoded)) $cast = $decoded;
            } elseif (is_array($castRaw)) {
                $cast = $castRaw;
            }
            $order = 0;
            foreach ($cast as $row) {
                $personId = $row['person_id'] ?? null;
                $name     = trim((string) ($row['name'] ?? ''));
                $person   = $this->resolvePerson($personId, $name);
                if (!$person) continue;
                $character = trim((string) ($row['character'] ?? ''));
                $title->credits()->attach($person->id, [
                    'department' => 'cast',
                    'job'        => 'Actor',
                    'character'  => $character ?: null,
                    'order'      => $order++,
                ]);
            }

            // DIRECTOR
            $director = $this->resolvePerson(
                $request->input('director_id'),
                trim((string) $request->input('director', ''))
            );
            if ($director) {
                $title->credits()->attach($director->id, [
                    'department' => 'directing',
                    'job'        => 'Director',
                    'order'      => 0,
                ]);
            }

            // WRITER
            $writer = $this->resolvePerson(
                $request->input('writer_id'),
                trim((string) $request->input('writer', ''))
            );
            if ($writer) {
                $title->credits()->attach($writer->id, [
                    'department' => 'writing',
                    'job'        => 'Writer',
                    'order'      => 0,
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('attachCredits failed: ' . $e->getMessage());
        }
    }

    /**
     * Resolve a person from a picker selection. Prefer an explicit id
     * (existing or just-created pending person); fall back to creating
     * one by name for legacy free-text input. Returns null if neither.
     */
    private function resolvePerson($personId, string $name): ?\App\Person
    {
        if ($personId !== null && $personId !== '' && is_numeric($personId)) {
            return \App\Person::withoutGlobalScope('approved')->find((int) $personId);
        }
        if ($name !== '') {
            return \App\Person::firstOrCreate(['name' => $name], [
                'allow_update' => false,
                'fully_synced' => true,
            ]);
        }
        return null;
    }

    private function detectExternalType(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $embedHosts = [
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be',
            'vimeo.com', 'player.vimeo.com',
            'dailymotion.com', 'www.dailymotion.com', 'dai.ly',
            'facebook.com', 'www.facebook.com', 'fb.watch',
        ];
        foreach ($embedHosts as $h) {
            if ($host === $h || str_ends_with($host, '.' . $h)) {
                return Video::VIDEO_TYPE_EMBED;
            }
        }
        // Direct file extension → playable in a <video> tag.
        if (preg_match('/\.(mp4|webm|ogg|m3u8|mov)$/i', $path)) {
            return Video::VIDEO_TYPE_DIRECT;
        }
        return Video::VIDEO_TYPE_EXTERNAL;
    }

    /**
     * DELETE /api/v1/creator/content/{id}
     */
    /**
     * PUT /api/v1/creator/content/{id}
     *
     * Update a title the creator owns. Deliberately mirrors store() field for
     * field so the dashboard can use ONE form for both adding and editing —
     * previously creators had no edit at all and were sent to the admin CMS
     * panel, which looks nothing like the upload form.
     *
     * Artwork is only replaced when a new file is supplied, and the moderation
     * status is left untouched (a metadata edit shouldn't silently unpublish).
     */
    public function update(Request $request, int $titleId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }

        // Ownership: the creator must own a video attached to this title.
        $owns = Video::where('title_id', $titleId)
            ->where('user_id', $user->id)
            ->exists();
        if (!$owns) {
            return response()->json(['message' => 'Not found or unauthorized.'], 404);
        }

        $title = Title::withoutGlobalScope('approved')->find($titleId);
        if (!$title) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $this->validate($request, [
            'title'          => 'required|string|min:2|max:250',
            'type'           => 'required|in:movie,short,series,documentary,poc',
            'year'           => 'nullable|integer|min:1900|max:2099',
            'description'    => 'nullable|string|max:5000',
            'tagline'        => 'nullable|string|max:250',
            'runtime'        => 'nullable|integer|min:1|max:1440',
            'genre'          => 'nullable|string|max:255',
            'language'       => 'nullable|string|max:50',
            'country'        => 'nullable|string|max:80',
            'release_date'   => 'nullable|date',
            'certification'  => 'nullable|string|max:10',
            'original_title' => 'nullable|string|max:250',
            'trailer'        => 'nullable|string|max:1000',
            'budget'         => 'nullable|integer|min:0',
            'revenue'        => 'nullable|integer|min:0',
            'imdb_id'        => 'nullable|string|max:20',
            'tmdb_id'        => 'nullable|integer|min:1',
            'cover'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'backdrop_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
        ]);

        $type = $request->input('type');

        $title->name           = $request->input('title');
        $title->type           = $type;
        $title->is_series      = $type === 'series';
        $title->year           = $request->input('year');
        $title->description    = $request->input('description');
        $title->tagline        = $request->input('tagline');
        $title->runtime        = $request->input('runtime');
        $title->genre          = $request->input('genre');
        $title->language       = $request->input('language');
        $title->country        = $request->input('country');
        $title->release_date   = $request->input('release_date');
        $title->certification  = $request->input('certification');
        $title->original_title = $request->input('original_title');
        $title->trailer        = $request->input('trailer');
        $title->budget  = $request->filled('budget') ? (int) $request->input('budget') : null;
        $title->revenue = $request->filled('revenue') ? (int) $request->input('revenue') : null;
        $title->imdb_id = $request->filled('imdb_id') ? $request->input('imdb_id') : null;
        $title->tmdb_id = $request->filled('tmdb_id') ? (int) $request->input('tmdb_id') : null;

        if ($request->hasFile('cover')) {
            $posterPath = $request->file('cover')->store('creator_content/covers', 'public');
            $title->poster = 'storage/' . $posterPath;
        }
        if ($request->hasFile('backdrop_image')) {
            $backdropPath = $request->file('backdrop_image')->store('creator_content/backdrops', 'public');
            $title->backdrop = 'storage/' . $backdropPath;
        }

        $title->save();

        // The form is the source of truth for this title's people, and
        // attachCredits() only ever attaches — so clear first to avoid
        // duplicating the director/writer/cast on every save.
        $title->credits()->detach();
        $this->attachCredits($title, $request);

        return response()->json(['status' => 'ok', 'title_id' => $title->id]);
    }

    public function destroy(Request $request, int $titleId): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (method_exists($user, 'isBlocked') && $user->isBlocked()) {
            return response()->json(['message' => 'Your account is blocked.'], 403);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }
        $userId = $user->id;

        $videos = Video::where('title_id', $titleId)->where('user_id', $userId)->get();
        if ($videos->isEmpty()) {
            return response()->json(['message' => 'Not found or unauthorized.'], 404);
        }

        // Drop the underlying media (local disk AND R2) before the rows go,
        // otherwise the bucket keeps paying to store files nothing can reach.
        app(\App\Services\Hvn\DeleteVideoMedia::class)->execute($videos);

        foreach ($videos as $video) {
            $video->delete();
        }

        // Title lookup needs to bypass the approved global scope so we can
        // resolve pending/rejected titles too.
        $title = Title::withoutGlobalScope('approved')->find($titleId);
        $titleSnapshotForNotif = $title ? clone $title : null;

        if ($title && !Video::where('title_id', $titleId)->exists()) {
            if ($title->poster && strpos($title->poster, '/storage/creator_content') === 0) {
                $rel = ltrim(str_replace('/storage/', '', $title->poster), '/');
                Storage::disk('public')->delete($rel);
            }
            $title->delete();
        }

        if ($titleSnapshotForNotif) {
            \App\User::notifyAdmins(new \App\Notifications\HvnContentDeleted($titleSnapshotForNotif, $user));
        }

        return response()->json(['deleted' => true]);
    }
}

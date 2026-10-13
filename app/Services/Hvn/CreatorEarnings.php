<?php

namespace App\Services\Hvn;

use App\Title;
use App\User;
use App\Video;
use App\VideoPlay;
use Common\Settings\Settings;
use Illuminate\Support\Facades\DB;

/**
 * The HVN Creator Earnings Program.
 *
 * Decides whether a play counts as an eligible view and, if so, writes one
 * priced row to the creator_earnings ledger.
 *
 * Two principles shape this:
 *
 *  - The rate is never hard-coded and never applied retroactively. Each row
 *    stores the rate in force when the view happened, so raising the rate
 *    later pays more for future views and leaves every statement already
 *    shown to a creator untouched.
 *
 *  - Crediting is idempotent. One ledger row per play id, enforced by a unique
 *    index, so repeated heartbeats on the same play cannot double-credit even
 *    if two requests race.
 */
class CreatorEarnings
{
    private $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get('earnings.enabled', false);
    }

    public function rate(): float
    {
        return (float) $this->settings->get('earnings.rate_per_view', 0.001);
    }

    public function minPayout(): float
    {
        return (float) $this->settings->get('earnings.min_payout', 25);
    }

    /**
     * Seconds of watch time a view must reach to be eligible.
     *
     * A flat floor (default 30s) applies to everything. The percentage gate is
     * off by default; when switched on it scales with runtime and is capped so
     * a feature film does not demand half an hour before it pays anything.
     */
    public function threshold(?Title $title): int
    {
        $floor = (int) $this->settings->get('earnings.min_seconds', 30);
        $percent = (float) $this->settings->get('earnings.min_percent', 0);

        if ($percent <= 0 || !$title || !$title->runtime) {
            return $floor;
        }

        // titles.runtime is in minutes.
        $runtimeSeconds = (int) $title->runtime * 60;
        $cap = (int) $this->settings->get('earnings.percent_cap_seconds', 600);
        $scaled = min($runtimeSeconds * ($percent / 100), $cap);

        return (int) max($floor, $scaled);
    }

    /**
     * Assess a play and credit it if it qualifies.
     *
     * Returns true only when a new ledger row was written.
     */
    public function assess(VideoPlay $play): bool
    {
        if (!$this->enabled() || $play->assessed_at) {
            return false;
        }

        $video = Video::find($play->video_id);
        if (!$video || !$video->user_id) {
            // Not creator-uploaded (TMDB imports carry no user_id).
            return $this->markAssessed($play);
        }

        // Only the feature itself earns. Trailers and clips are marketing.
        if (($video->category ?? null) !== 'full') {
            return $this->markAssessed($play);
        }

        $title = Title::withoutGlobalScope('approved')->find($video->title_id);
        if (!$title || $title->status !== 'approved') {
            return $this->markAssessed($play);
        }

        if ((int) $play->seconds_watched < $this->threshold($title)) {
            // Not yet -- leave unassessed so a later heartbeat can qualify it.
            return false;
        }

        if (!$this->viewerCounts($play, $video)) {
            return $this->markAssessed($play);
        }

        if ($this->alreadyCreditedRecently($play, $video)) {
            return $this->markAssessed($play);
        }

        $rate = $this->rate();

        try {
            DB::table('creator_earnings')->insert([
                'creator_id' => $video->user_id,
                'title_id' => $video->title_id,
                'video_id' => $video->id,
                'play_id' => $play->id,
                'viewer_id' => $play->user_id,
                'seconds_watched' => (int) $play->seconds_watched,
                'rate' => $rate,
                'amount' => $rate,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Unique index on play_id: a concurrent heartbeat got there first.
            // That is the index doing its job, not an error.
            $this->markAssessed($play);
            return false;
        }

        $this->markAssessed($play);

        return true;
    }

    /**
     * Anonymous views, the creator's own views and staff views do not earn.
     */
    private function viewerCounts(VideoPlay $play, Video $video): bool
    {
        if (!$play->user_id) {
            return false;
        }

        if ((int) $play->user_id === (int) $video->user_id) {
            return false;
        }

        if ((bool) $this->settings->get('earnings.exclude_staff', true)) {
            $viewer = User::find($play->user_id);
            if (
                $viewer &&
                method_exists($viewer, 'hasPermission') &&
                $viewer->hasPermission('admin')
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * One eligible view per viewer per title per window, so replaying a title
     * all afternoon earns once.
     */
    private function alreadyCreditedRecently(VideoPlay $play, Video $video): bool
    {
        $hours = (int) $this->settings->get('earnings.dedupe_hours', 24);
        if ($hours <= 0) {
            return false;
        }

        return DB::table('creator_earnings')
            ->where('title_id', $video->title_id)
            ->where('viewer_id', $play->user_id)
            ->where('created_at', '>=', now()->subHours($hours))
            ->exists();
    }

    private function markAssessed(VideoPlay $play): bool
    {
        VideoPlay::where('id', $play->id)->update(['assessed_at' => now()]);
        return false;
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\Hvn\CreatorEarnings;
use App\Video;
use App\VideoPlay;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatorEarningsController extends BaseController
{
    private $earnings;

    public function __construct(CreatorEarnings $earnings)
    {
        $this->earnings = $earnings;
    }

    /**
     * POST /secure/videos/{video}/watch-progress
     *
     * Heartbeat from the player carrying ACCUMULATED seconds actually played,
     * not the playhead position -- otherwise dragging the scrubber to the end
     * would look like a complete view.
     *
     * Deliberately separate from log-play: time_watched there is a resume
     * pointer that is reset to 0 on completion, so it can never be used to
     * decide what to pay.
     */
    public function watchProgress(Request $request, int $videoId): JsonResponse
    {
        $request->validate([
            'seconds' => 'required|numeric|min:0|max:86400',
        ]);

        $user = $request->user();
        if (!$user) {
            // Anonymous views never earn, so there is nothing to record.
            return response()->json(['recorded' => false]);
        }

        $video = Video::find($videoId);
        if (!$video) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $seconds = (int) floor((float) $request->input('seconds'));

        // Reuse this viewer's current play rather than creating a row per
        // heartbeat. A gap longer than the dedupe window is a new sitting.
        $play = VideoPlay::where('video_id', $videoId)
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHours(12))
            ->orderByDesc('created_at')
            ->first();

        if (!$play) {
            $play = VideoPlay::create([
                'video_id' => $videoId,
                'user_id' => $user->id,
                'created_at' => now(),
                'ip' => $request->ip(),
                'seconds_watched' => 0,
            ]);
        }

        // Monotonic: seeking backwards and re-watching must not reduce the
        // total, and a stale heartbeat arriving late must not either.
        if ($seconds > (int) $play->seconds_watched) {
            $play->seconds_watched = $seconds;
            $play->save();
        }

        $credited = $this->earnings->assess($play->fresh());

        return response()->json([
            'recorded' => true,
            'credited' => $credited,
        ]);
    }

    /**
     * GET /secure/creator/earnings
     *
     * The creator's own statement. Every figure here is derived from the
     * ledger, so it matches what an admin sees to the cent.
     */
    public function creatorSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if ($user->role !== 'creator') {
            return response()->json(['message' => 'Forbidden — creators only.'], 403);
        }

        $creatorId = $user->id;

        $titleIds = Video::where('user_id', $creatorId)
            ->whereNotNull('title_id')
            ->pluck('title_id')
            ->unique();

        // Every play of this creator's videos, eligible or not, so the
        // dashboard can be honest about the gap between the two.
        $totalViews = (int) DB::table('video_plays')
            ->join('videos', 'videos.id', '=', 'video_plays.video_id')
            ->where('videos.user_id', $creatorId)
            ->count();

        $ledger = DB::table('creator_earnings')->where('creator_id', $creatorId);

        $eligibleViews = (int) (clone $ledger)->count();
        $totalEarned = (float) (clone $ledger)->sum('amount');
        $unpaid = (float) (clone $ledger)->whereNull('payout_id')->sum('amount');

        $byTitle = DB::table('creator_earnings')
            ->where('creator_id', $creatorId)
            ->select('title_id', DB::raw('COUNT(*) as views'), DB::raw('SUM(amount) as earned'))
            ->groupBy('title_id')
            ->orderByDesc('earned')
            ->get();

        $names = \App\Title::withoutGlobalScope('approved')
            ->whereIn('id', $byTitle->pluck('title_id'))
            ->pluck('name', 'id');

        $payouts = DB::table('creator_payouts')
            ->where('creator_id', $creatorId)
            ->orderByDesc('created_at')
            ->get([
                'id', 'amount', 'status', 'method', 'reference',
                'period_start', 'period_end', 'paid_at', 'created_at',
            ]);

        return response()->json([
            'enabled' => $this->earnings->enabled(),
            'rate' => $this->earnings->rate(),
            'min_payout' => $this->earnings->minPayout(),
            'min_seconds' => (int) app(\Common\Settings\Settings::class)->get('earnings.min_seconds', 30),
            'dedupe_hours' => (int) app(\Common\Settings\Settings::class)->get('earnings.dedupe_hours', 24),
            'totals' => [
                'titles' => $titleIds->count(),
                'total_views' => $totalViews,
                'eligible_views' => $eligibleViews,
                'total_earned' => round($totalEarned, 4),
                'unpaid' => round($unpaid, 4),
                'paid' => round($totalEarned - $unpaid, 4),
            ],
            'by_title' => $byTitle->map(fn($r) => [
                'title_id' => $r->title_id,
                'name' => $names[$r->title_id] ?? 'Untitled',
                'eligible_views' => (int) $r->views,
                'earned' => round((float) $r->earned, 4),
            ]),
            'payouts' => $payouts,
        ]);
    }
}

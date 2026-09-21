<?php

namespace App;

use Common\Search\Searchable;
use DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int positive_votes
 * @property int negative_votes
 * @property string name
 * @property string description
 * @property string thumbnail
 * @property int season_num
 * @property int episode_num
 * @property-read Episode episode
 */
class Video extends Model
{
    use Searchable;

    const VIDEO_TYPE_EMBED = 'embed';
    const VIDEO_TYPE_DIRECT = 'direct';
    const VIDEO_TYPE_EXTERNAL = 'external';
    const MODEL_TYPE = 'video';

    protected $guarded = ['id'];
    protected $appends = ['score', 'model_type', 'moderation_status'];

    /**
     * 3-state moderation status sourced from the parent title:
     * 'approved' | 'pending' | 'rejected'. Non-creator uploads
     * (TMDB imports etc. — no user_id on the video) always 'approved'.
     */
    public function getModerationStatusAttribute(): string
    {
        if (empty($this->attributes['user_id'])) return 'approved';
        // bypass Title's "approved" global scope so the lookup works
        // for pending/rejected titles too.
        $status = Title::withoutGlobalScope('approved')
            ->where('id', $this->attributes['title_id'] ?? 0)
            ->value('status');
        return $status ?: 'approved';
    }

    protected $casts = [
        'negative_votes' => 'integer',
        'positive_votes' => 'integer',
        'order' => 'integer',
        'approved' => 'boolean',
        'reports' => 'integer',
        'title_id' => 'integer',
        'id' => 'integer',
        'user_id' => 'integer',
    ];

    /**
     * @return BelongsTo
     */
    public function title()
    {
        return $this->belongsTo(Title::class);
    }

    /**
     * Creator who uploaded this video (creator_content uploads only —
     * TMDB/imported videos have user_id NULL).
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ratings()
    {
        return $this->hasMany(VideoRating::class);
    }

    public function reports()
    {
        return $this->hasMany(VideoReport::class);
    }

    public function captions()
    {
        return $this->hasMany(VideoCaption::class)->orderBy('order', 'asc');
    }

    public function plays()
    {
        return $this->hasMany(VideoPlay::class);
    }

    public function latestPlay()
    {
        return $this->hasOne(VideoPlay::class)->orderBy('created_at', 'desc');
    }

    public function episode()
    {
        return $this->belongsTo(Episode::class);
    }

    public function getScoreAttribute()
    {
        $total = $this->positive_votes + $this->negative_votes;
        if (!$total) {
            return null;
        }
        return round(($this->positive_votes / $total) * 100);
    }

    public function scopeSelectScore(Builder $query)
    {
        return $query->select([
            '*',
            DB::raw('((positive_votes + 1.9208) / (positive_votes + negative_votes) -.96 * SQRT((positive_votes * negative_votes) / (positive_votes + negative_votes) + 0.9604) /
         (positive_votes + negative_votes)) / (1 + 3.8416 / (positive_votes + negative_votes))
         AS score'),
        ]);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at->timestamp ?? '_null',
            'updated_at' => $this->updated_at->timestamp ?? '_null',
        ];
    }

    public static function filterableFields(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    public function toNormalizedArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->thumbnail,
            'model_type' => self::MODEL_TYPE,
        ];
    }

    public static function getModelTypeAttribute(): string
    {
        return self::MODEL_TYPE;
    }

    /**
     * HVN: full-length videos are for signed-in members only.
     *
     * The permission check used to live only in the Angular client, which meant
     * anyone could read the playable URL straight out of the JSON for
     * /secure/titles/{id} without logging in. Withholding the URL here covers
     * every endpoint that serialises a Video at once, instead of each
     * controller having to remember to do it.
     *
     * Trailers and clips stay open on purpose so titles remain browsable and
     * shareable to logged-out visitors -- only the feature itself is gated.
     * Internal PHP reads of $video->url are untouched, so deletion, playback
     * logging and the creator dashboard keep working.
     */
    public function toArray()
    {
        $data = parent::toArray();

        if (
            ($data['category'] ?? null) === 'full' &&
            !$this->viewerMayPlayFullVideo()
        ) {
            $data['url'] = null;
            $data['requires_auth'] = true;
        }

        return $data;
    }

    private function viewerMayPlayFullVideo(): bool
    {
        $user = Auth::user();

        // Guests never get the URL, regardless of what the guests role happens
        // to be configured with in the admin area.
        if (!$user) {
            return false;
        }

        if ($user->hasPermission('videos.play')) {
            return true;
        }

        // A creator can always reach their own upload, even if their role
        // somehow lacks videos.play.
        return (int) ($this->attributes['user_id'] ?? 0) === (int) $user->id;
    }
}

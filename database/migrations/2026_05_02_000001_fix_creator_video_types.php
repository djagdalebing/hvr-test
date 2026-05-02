<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixCreatorVideoTypes extends Migration
{
    /**
     * Backfill the type column for creator-uploaded videos that were saved
     * with the invalid value 'video'. The frontend player only renders videos
     * whose type is one of: direct, embed, external — anything else falls
     * through to the placeholder image.
     */
    public function up(): void
    {
        // Self-hosted files (source='local') play directly in a <video> tag.
        DB::table('videos')
            ->whereNotNull('user_id')
            ->where('type', 'video')
            ->where('source', 'local')
            ->update(['type' => 'direct']);

        // External URLs to recognised embed hosts (YouTube, Vimeo, etc.).
        $embedHosts = [
            'youtube.com', 'youtu.be',
            'vimeo.com',
            'dailymotion.com', 'dai.ly',
            'facebook.com', 'fb.watch',
        ];
        foreach ($embedHosts as $host) {
            DB::table('videos')
                ->whereNotNull('user_id')
                ->where('type', 'video')
                ->where('url', 'like', '%' . $host . '%')
                ->update(['type' => 'embed']);
        }

        // External direct file URLs ending in a recognised extension.
        DB::table('videos')
            ->whereNotNull('user_id')
            ->where('type', 'video')
            ->where(function ($q) {
                $q->where('url', 'like', '%.mp4')
                  ->orWhere('url', 'like', '%.webm')
                  ->orWhere('url', 'like', '%.ogg')
                  ->orWhere('url', 'like', '%.m3u8')
                  ->orWhere('url', 'like', '%.mov');
            })
            ->update(['type' => 'direct']);

        // Anything still left → generic external link.
        DB::table('videos')
            ->whereNotNull('user_id')
            ->where('type', 'video')
            ->update(['type' => 'external']);
    }

    public function down(): void
    {
        // No-op: we don't want to revert valid types back to the broken 'video' value.
    }
}

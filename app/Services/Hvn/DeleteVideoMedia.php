<?php

namespace App\Services\Hvn;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Remove the stored media behind a set of videos.
 *
 * Deleting a title only ever dropped files on local disk -- anything
 * uploaded to Cloudflare R2 stayed in the bucket permanently, costing
 * storage for content nobody could reach any more. R2 objects are addressed
 * by the key encoded in videos.url; see Video::toArray() for the other half
 * of that convention.
 *
 * Best-effort throughout: failing to remove a file is not a reason to fail
 * the delete the user actually asked for, so problems are logged and
 * swallowed rather than thrown.
 */
class DeleteVideoMedia
{
    /**
     * @param iterable $videos Video models (needs url + source).
     */
    public function execute($videos): void
    {
        foreach ($videos as $video) {
            $url = $video->url ?? null;
            $source = $video->source ?? null;

            if (!$url) {
                continue;
            }

            try {
                if ($source === 'r2') {
                    $this->deleteR2Object($url);
                } elseif ($source === 'local') {
                    $rel = ltrim(str_replace('/storage/', '', $url), '/');
                    Storage::disk('public')->delete($rel);
                }
                // External links (YouTube, Vimeo, arbitrary URLs) are not ours
                // to delete -- nothing to do.
            } catch (\Throwable $e) {
                \Log::warning(
                    'Failed deleting media for video ' .
                        ($video->id ?? '?') .
                        ': ' .
                        $e->getMessage(),
                );
            }
        }
    }

    private function deleteR2Object(string $url): void
    {
        $base = rtrim((string) config('filesystems.disks.r2.url'), '/');

        $key =
            $base && Str::startsWith($url, $base . '/')
                ? substr($url, strlen($base) + 1)
                : ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($key !== '') {
            Storage::disk('r2')->delete($key);
        }
    }
}

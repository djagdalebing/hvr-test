<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cleans up the genre/keyword tags:
 *  1. Ensures a clean set of standard film genres exists (type = genre).
 *     Because `tags.name` is globally unique, standard genre slugs that already
 *     exist as *keywords* (e.g. "action", "drama") are repurposed into genres
 *     instead of failing on insert.
 *  2. Removes TV-format "genres" that don't fit a film platform
 *     (soap, talk, news, reality, kids, action-adventure, sci-fi-fantasy).
 *  3. Removes explicit adult KEYWORD tags that were imported into the DB and
 *     were surfacing in the tag pickers.
 *
 * The genre picker itself was ALSO fixed in TagController@index (it was
 * ignoring the type filter and showing keyword tags in the genre selector).
 */
class CleanGenresAndAdultTags extends Migration
{
    public function up()
    {
        if (!DB::getSchemaBuilder()->hasTable('tags')) {
            return;
        }

        // ---- 1. Standard film genres --------------------------------------
        $genres = [
            'Action', 'Adventure', 'Animation', 'Comedy', 'Crime',
            'Documentary', 'Drama', 'Family', 'Fantasy', 'History',
            'Horror', 'Music', 'Mystery', 'Romance', 'Science Fiction',
            'Thriller', 'War', 'Western',
        ];
        foreach ($genres as $name) {
            $slug = Str::slug($name);
            $existing = DB::table('tags')->where('name', $slug)->first();
            if ($existing) {
                // Repurpose a colliding tag (usually a keyword) into a genre.
                if ($existing->type !== 'genre') {
                    DB::table('tags')->where('id', $existing->id)->update([
                        'type' => 'genre',
                        'display_name' => $name,
                        'updated_at' => now(),
                    ]);
                }
            } else {
                DB::table('tags')->insert([
                    'name' => $slug,
                    'display_name' => $name,
                    'type' => 'genre',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // ---- 2. Remove TV-format "genres" ---------------------------------
        $badGenres = ['action-adventure', 'kids', 'news', 'reality', 'sci-fi-fantasy', 'soap', 'talk'];
        $badGenreIds = DB::table('tags')
            ->where('type', 'genre')
            ->whereIn('name', $badGenres)
            ->pluck('id');
        if ($badGenreIds->isNotEmpty()) {
            DB::table('taggables')->whereIn('tag_id', $badGenreIds)->delete();
            DB::table('tags')->whereIn('id', $badGenreIds)->delete();
        }

        // ---- 3. Remove explicit adult keyword tags ------------------------
        // Tight, unambiguous list so legitimate film keywords (film-noir,
        // female-nudity, sex-scene, etc.) are left untouched.
        $adultExact = [
            'double-penetration', 'anal', 'anal-sex', 'group-sex', 'pink-film',
            'gangbang', 'gang-bang', 'bukkake', 'creampie', 'cumshot', 'cum-shot',
            'deepthroat', 'deep-throat', 'fisting', 'blowjob', 'handjob', 'hand-job',
        ];
        $adultIds = DB::table('tags')
            ->where('type', 'keyword')
            ->where(function ($q) use ($adultExact) {
                $q->whereIn('name', $adultExact)
                    ->orWhere('name', 'like', 'anal-%')
                    ->orWhere('name', 'like', '%porn%');
            })
            ->pluck('id');
        if ($adultIds->isNotEmpty()) {
            DB::table('taggables')->whereIn('tag_id', $adultIds)->delete();
            DB::table('tags')->whereIn('id', $adultIds)->delete();
        }
    }

    public function down()
    {
        // Data cleanup is intentionally not reversible.
    }
}

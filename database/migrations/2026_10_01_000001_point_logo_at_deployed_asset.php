<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Serve the site logo from the deployed bundle instead of uploaded media.
 *
 * The logo lived in storage/branding_media as an uploaded file, owned by the
 * user who uploaded it. Deleting that user ran PermanentlyDeleteEntries over
 * their file entries, which took the logo files off disk while the settings
 * still pointed at them -- so the navbar rendered a broken image.
 *
 * The same files now ship in public/client/branding, where they are part of
 * the deploy rather than someone's uploaded media, so no account deletion can
 * remove them again. Same images, same mapping: the dark wordmark for light
 * backgrounds, the white one for dark.
 */
class PointLogoAtDeployedAsset extends Migration
{
    const LOGOS = [
        'branding.logo_dark' => 'client/branding/logo-dark.png',
        'branding.logo_light' => 'client/branding/logo-light.png',
    ];

    public function up()
    {
        if (!DB::getSchemaBuilder()->hasTable('settings')) {
            return;
        }

        foreach (self::LOGOS as $name => $path) {
            $exists = DB::table('settings')->where('name', $name)->exists();

            if ($exists) {
                DB::table('settings')
                    ->where('name', $name)
                    ->update(['value' => $path]);
            } else {
                DB::table('settings')->insert([
                    'name' => $name,
                    'value' => $path,
                    'private' => 0,
                ]);
            }
        }
    }

    public function down()
    {
        // Not reversible: the files the old values pointed at no longer exist.
    }
}
